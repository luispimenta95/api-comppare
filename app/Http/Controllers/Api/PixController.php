<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use App\Mail\EmailPix;
use App\Models\PagamentoPix;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Util\Helper;
use Carbon\Carbon;

class PixController extends Controller
{
    // Constantes
    private const TIMEOUT_REQUEST = 30;
    private const EXPIRACAO_COB = 3600;
    private const POLITICA_RETENTATIVA = "NAO_PERMITE";

    // Propriedades da classe
    private ApiEfi $apiEfi;
    private string $enviroment;
    private string $certificadoPath;
    private string $chavePix;
    private Usuarios $usuario;
    private Planos $plano;
    private string $numeroContrato;
    private string $dataInicial;
    private string $frequencia;

    public function __construct()
    {
        $this->apiEfi = new ApiEfi();
        $this->enviroment = config('app.env');
        $this->initializeCertificadoPath();
        $this->initializeChavePix();
    }

    private function initializeCertificadoPath(): void
    {
        $this->certificadoPath = $this->enviroment == "local"
            ? storage_path('app/certificates/hml.pem')
            : storage_path('app/certificates/prd.pem');
    }

    private function initializeChavePix(): void
    {
        $this->chavePix = env('CHAVE_PIX');
    }

    public function criarCobranca(Request $request): JsonResponse
    {
        try {
            $this->initializeRequestData($request);
            $pixData = $this->executarFluxoPixRecorrente();
            $this->salvarPagamentoPix($pixData);
            $this->enviarEmailPix($pixData['pixCopiaECola'], $pixData['txid']);
            return $this->buildSuccessResponse($pixData);
        } catch (\Exception $e) {
            Log::error('Erro geral no fluxo PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno no processamento PIX',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function initializeRequestData(Request $request): void
    {
        $this->usuario = Usuarios::find($request->usuario);
        $this->plano = Planos::find($request->plano);
        if (!$this->usuario || !$this->plano) {
            Log::error('Usuário ou plano não encontrado na inicialização dos dados', [
                'usuario_id_solicitado' => $request->usuario,
                'plano_id_solicitado' => $request->plano,
                'usuario_encontrado' => !is_null($this->usuario),
                'plano_encontrado' => !is_null($this->plano),
                'request_data' => $request->all()
            ]);
            throw new \InvalidArgumentException('Usuário ou plano não encontrado');
        }
        $this->numeroContrato = $this->generateNumeroContrato();
        $this->dataInicial = now()->addDay()->toDateString();
        $this->frequencia = $this->determineFrequencia();
    }

    private function generateNumeroContrato(): string
    {
        return strval(mt_rand(10000000, 99999999));
    }

    private function determineFrequencia(): string
    {
        return $this->plano->frequenciaCobranca == 12
            ? Helper::PERIODICIDADE_ANUAL
            : Helper::PERIODICIDADE_MENSAL;
    }

    private function executarFluxoPixRecorrente(): array
    {
        $txid = $this->definirTxid();
        $cobResponse = $this->criarCob($txid);
        $this->validateResponse($cobResponse, 'COB');
        $locrecResponse = $this->criarLocationRec();
        $this->validateResponse($locrecResponse, 'LOCREC');
        $locrecId = $locrecResponse['data']['id'] ?? null;
        if (!$locrecId) {
            Log::error('ID do Location Rec não encontrado na resposta da API', [
                'locrecResponse' => $locrecResponse,
                'usuario_id' => $this->usuario->id,
                'plano_id' => $this->plano->id
            ]);
            throw new \RuntimeException('ID do Location Rec não encontrado');
        }
        $cobAtiva = $this->aguardarCobAtiva($txid);
        if (!$cobAtiva) {
            throw new \RuntimeException('COB não ficou ativa após múltiplas tentativas. Tente novamente em alguns minutos.');
        }
        $recResponse = $this->criarRecComRetry($txid, $locrecId);
        $this->validateResponse($recResponse, 'REC');
        $recId = $recResponse['data']['idRec'] ?? null;
        if (!$recId) {
            Log::error('ID do REC não encontrado na resposta da API', [
                'recResponse' => $recResponse,
                'txid' => $txid,
                'locrecId' => $locrecId,
                'usuario_id' => $this->usuario->id
            ]);
            throw new \RuntimeException('ID do REC não encontrado');
        }
        $qrcodeResponse = $this->resgatarQRCode($recId, $txid);
        $this->validateResponse($qrcodeResponse, 'QRCODE');
        $pixCopiaECola = $qrcodeResponse['data']['dadosQR']['pixCopiaECola'] ?? null;
        if (!$pixCopiaECola) {
            Log::error('Código PIX não gerado na resposta da API', [
                'qrcodeResponse' => $qrcodeResponse,
                'recId' => $recId,
                'txid' => $txid,
                'usuario_id' => $this->usuario->id
            ]);
            throw new \RuntimeException('Código PIX não gerado');
        }
        return [
            'txid' => $txid,
            'pixCopiaECola' => $pixCopiaECola,
            'cobResponse' => $cobResponse,
            'locrecResponse' => $locrecResponse,
            'recResponse' => $recResponse,
            'qrcodeResponse' => $qrcodeResponse,
            'locrecId' => $locrecId,
            'recId' => $recId
        ];
    }

    private function validateResponse(array $response, string $step): void
    {
        if (!$response['success']) {
            $errorDetails = [
                'step' => $step,
                'response' => $response,
                'http_code' => $response['http_code'] ?? null,
                'error' => $response['error'] ?? 'Erro desconhecido',
                'url' => $response['url'] ?? null,
                'body' => $response['body'] ?? null
            ];
            $errorMessage = "Erro no passo {$step}";
            if (isset($response['data']) && is_array($response['data'])) {
                if ($step === 'REC' && isset($response['data']['violacoes'])) {
                    $violacoes = $response['data']['violacoes'];
                    foreach ($violacoes as $violacao) {
                        if (isset($violacao['razao'])) {
                            if (strpos($violacao['razao'], 'não está ativa') !== false) {
                                $errorMessage = "Erro no passo REC: COB não está ativa para criação de recorrência. Aguarde alguns segundos e tente novamente.";
                                Log::warning('Problema de timing entre COB e REC', [
                                    'violacao' => $violacao,
                                    'sugestao' => 'Implementar retry com delay maior ou verificar status do COB'
                                ]);
                                break;
                            } else {
                                $errorMessage = "Erro no passo REC: " . $violacao['razao'];
                            }
                        }
                    }
                } elseif (isset($response['data']['detail'])) {
                    $errorMessage .= ": " . $response['data']['detail'];
                } elseif (isset($response['data']['mensagem'])) {
                    $errorMessage .= ": " . $response['data']['mensagem'];
                } elseif (isset($response['data']['error'])) {
                    $errorMessage .= ": " . $response['data']['error'];
                }
            } elseif (!empty($response['error'])) {
                $errorMessage .= ": " . $response['error'];
            } else {
                $errorMessage .= ": Erro HTTP " . ($response['http_code'] ?? 'desconhecido');
            }
            Log::error("Erro na validação da resposta da API - Passo {$step}", $errorDetails);
            throw new \RuntimeException($errorMessage);
        }
    }

    private function salvarPagamentoPix(array $pixData): PagamentoPix
    {
        try {
            $pagamentoPix = PagamentoPix::create([
                'idUsuario' => $this->usuario->id,
                'txid' => $pixData['txid'],
                'numeroContrato' => $this->numeroContrato,
                'pixCopiaECola' => $pixData['pixCopiaECola'],
                'valor' => number_format($this->plano->valor, 2, '.', ''),
                'chavePixRecebedor' => $this->chavePix,
                'nomeDevedor' => strtoupper($this->usuario->primeiroNome . " " . $this->usuario->sobrenome),
                'cpfDevedor' => $this->usuario->cpf,
                'locationId' => $pixData['locrecId'],
                'recId' => $pixData['recId'],
                'status' => 'ATIVA',
                'statusPagamento' => 'PENDENTE',
                'dataInicial' => $this->dataInicial,
                'periodicidade' => $this->frequencia,
                'objeto' => $this->plano->nome,
                'responseApiCompleta' => [
                    'cob' => $pixData['cobResponse']['data'],
                    'locrec' => $pixData['locrecResponse']['data'],
                    'rec' => $pixData['recResponse']['data'],
                    'qrcode' => $pixData['qrcodeResponse']['data']
                ]
            ]);
            return $pagamentoPix;
        } catch (\Exception $e) {
            Log::error('Erro ao salvar pagamento PIX', [
                'error' => $e->getMessage(),
                'txid' => $pixData['txid']
            ]);
            throw $e;
        }
    }

    private function enviarEmailPix(string $pixCopiaECola, string $txid): void
    {
        try {
            $dadosParaEmail = $this->buildEmailData($pixCopiaECola, $txid);
            Mail::to($this->usuario->email)->send(new EmailPix($dadosParaEmail));
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email PIX', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'email_destino' => $this->usuario->email,
                'txid' => $txid
            ]);
        }
    }

    private function buildEmailData(string $pixCopiaECola, string $txid): array
    {
        return [
            'to' => $this->usuario->email,
            'body' => [
                'nome' => strtoupper($this->usuario->primeiroNome . " " . $this->usuario->sobrenome),
                'valor' => number_format($this->plano->valor, 2, '.', ''),
                'pixCopiaECola' => $pixCopiaECola,
                'contrato' => $this->numeroContrato,
                'objeto' => $this->plano->nome,
                'periodicidade' => $this->frequencia,
                'dataInicial' => $this->dataInicial,
                'dataFinal' => null,
                'txid' => $txid
            ]
        ];
    }

    private function buildSuccessResponse(array $pixData): JsonResponse
    {
        return response()->json([
            'codRetorno' => 200,
            'message' => 'Cobrança PIX criada com sucesso',
            'data' => [
                'pix' => $pixData['pixCopiaECola']
            ]
        ]);
    }

    private function definirTxid(): string
    {
        return md5(uniqid(rand(), true));
    }

    private function criarCob(string $txid): array
    {
        $url = $this->buildApiUrl("/v2/cob/{$txid}");
        $body = json_encode([
            "calendario" => [
                "expiracao" => self::EXPIRACAO_COB
            ],
            "devedor" => [
                "cpf" => $this->usuario->cpf,
                "nome" => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome
            ],
            "valor" => [
                "original" => number_format($this->plano->valor, 2, '.', '')
            ],
            "chave" => $this->chavePix
        ]);
        return $this->executeApiRequest($url, 'PUT', $body);
    }

    private function criarLocationRec(): array
    {
        $url = $this->buildApiUrl("/v2/locrec");
        return $this->executeApiRequest($url);
    }

    private function criarRecComRetry(string $txid, $locrecId, int $maxTentativas = 3): array
    {
        $delays = [5, 10, 15];
        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            $recResponse = $this->criarRec($txid, $locrecId);
            if ($recResponse['success']) {
                return $recResponse;
            }
            $isCobNaoAtiva = false;
            if (isset($recResponse['data']['violacoes'])) {
                foreach ($recResponse['data']['violacoes'] as $violacao) {
                    if (isset($violacao['razao']) && strpos($violacao['razao'], 'não está ativa') !== false) {
                        $isCobNaoAtiva = true;
                        break;
                    }
                }
            }
            if (!$isCobNaoAtiva) {
                Log::warning("Erro na criação de REC não relacionado a COB inativa, não tentando novamente", [
                    'tentativa' => $tentativa,
                    'error_response' => $recResponse
                ]);
                return $recResponse;
            }
            if ($tentativa < $maxTentativas) {
                $delay = $delays[$tentativa - 1];
                sleep($delay);
                $cobStatus = $this->verificarStatusCob($txid);
            }
        }
        Log::error("Falha na criação de REC após {$maxTentativas} tentativas", [
            'txid' => $txid,
            'locrecId' => $locrecId,
            'ultimo_response' => $recResponse
        ]);
        return $recResponse;
    }

    private function criarRec(string $txid, $locrecId): array
    {
        $url = $this->buildApiUrl("/v2/rec");
        $body = json_encode([
            "vinculo" => [
                "contrato" => $this->numeroContrato,
                "devedor" => [
                    "cpf" => $this->usuario->cpf,
                    "nome" => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome
                ],
                "objeto" => $this->plano->nome
            ],
            "calendario" => [
                "dataInicial" => $this->dataInicial,
                "periodicidade" => $this->frequencia,
            ],
            "valor" => [
                "valorRec" => number_format($this->plano->valor, 2, '.', '')
            ],
            "politicaRetentativa" => self::POLITICA_RETENTATIVA,
            "loc" => $locrecId,
            "ativacao" => [
                "dadosJornada" => [
                    "txid" => $txid
                ]
            ]
        ]);
        return $this->executeApiRequest($url, null, $body);
    }

    private function resgatarQRCode(string $recId, string $txid): array
    {
        $url = $this->buildApiUrl("/v2/rec/{$recId}?txid={$txid}");
        return $this->executeApiRequest($url, 'GET');
    }

    private function aguardarCobAtiva(string $txid, int $maxTentativas = 5): bool
    {
        $delays = [3, 5, 8, 12, 20];
        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            if ($tentativa > 1) {
                $delay = $delays[$tentativa - 2];
                sleep($delay);
            }
            $cobStatusResponse = $this->verificarStatusCob($txid);
            if ($cobStatusResponse['success'] && isset($cobStatusResponse['data']['status'])) {
                $status = $cobStatusResponse['data']['status'];
                $delay = $delays[$tentativa - 2];
                sleep($delay);
            }
            $cobStatusResponse = $this->verificarStatusCob($txid);
            if ($cobStatusResponse['success'] && isset($cobStatusResponse['data']['status'])) {
                $status = $cobStatusResponse['data']['status'];
                if ($status === 'ATIVA') {
                    return true;
                }
            } else {
                Log::error("Erro ao verificar status do COB na tentativa {$tentativa}", [
                    'txid' => $txid,
                    'response' => $cobStatusResponse
                ]);
            }
        }
        Log::error("COB não ficou ativa após {$maxTentativas} tentativas", [
            'txid' => $txid,
            'tempo_total_espera' => array_sum($delays) . ' segundos'
        ]);
        return false;
    }

    private function verificarStatusCob(string $txid): array
    {
        $url = $this->buildApiUrl("/v2/cob/{$txid}");
        return $this->executeApiRequest($url, 'GET');
    }

    private function buildApiUrl(string $endpoint): string
    {
        $baseUrl = $this->enviroment === 'local'
            ? env('URL_API_PIX_LOCAL')
            : env('URL_API_PIX_PRODUCAO');
        return $baseUrl . $endpoint;
    }

    private function executeApiRequest(string $url, ?string $method = 'POST', ?string $body = null): array
    {
        if (!file_exists($this->certificadoPath)) {
            Log::error('Certificado não encontrado', [
                'path' => $this->certificadoPath,
                'environment' => $this->enviroment
            ]);
            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado não encontrado: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }
        if (!is_readable($this->certificadoPath)) {
            Log::error('Certificado não é legível', [
                'path' => $this->certificadoPath,
                'permissions' => substr(sprintf('%o', fileperms($this->certificadoPath)), -4)
            ]);
            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado não é legível: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }
        $curl = curl_init();
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUEST,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_SSLCERTTYPE => "PEM",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
        ];
        if ($this->enviroment === 'local') {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        } else {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
        }
        if ($body) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $curlOptions);
        $startTime = microtime(true);
        $response = curl_exec($curl);
        $executionTime = microtime(true) - $startTime;
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($curl);
        $error = curl_error($curl);
        curl_close($curl);
        $decodedResponse = null;
        if ($response) {
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Erro ao decodificar JSON da resposta da API', [
                    'json_error' => json_last_error_msg(),
                    'response_length' => strlen($response),
                    'response_preview' => substr($response, 0, 500),
                    'url' => $url
                ]);
            }
        }
        return [
            'success' => !$error && ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'error' => $error,
            'data' => $decodedResponse,
            'raw_response' => $response,
            'url' => $url,
            'body' => $body,
            'execution_time' => $executionTime,
            'curl_info' => $curlInfo
        ];
    }

    public function registrarWebhook(): JsonResponse
    {
        try {
            $skipMtlsHeader = 'true';
            $webhookUrl = env('APP_URL') . '/api/pix/atualizar?ignorar=';
            $url = $this->buildApiUrl("/v2/webhookrec");
            $body = json_encode([
                "webhookUrl" => $webhookUrl
            ]);
            $response = $this->executeApiRequestWithExtraHeaders($url, 'PUT', $body, [
                "x-skip-mtls-checking: $skipMtlsHeader"
            ]);
            if (!$response['success']) {
                Log::error('Erro ao registrar webhook PIX', [
                    'http_code' => $response['http_code'],
                    'error' => $response['error'],
                    'data' => $response['data']
                ]);
                return response()->json([
                    'codRetorno' => 500,
                    'message' => 'Falha ao registrar webhook Pix',
                    'detalhes' => $response
                ], 500);
            }
            return response()->json([
                'codRetorno' => 200,
                'message' => 'Webhook PIX registrado com sucesso',
                'data' => $response['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao registrar webhook PIX', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao registrar webhook Pix',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function atualizarCobranca(Request $request): JsonResponse
    {
        if (isset($request->cobsr)) {
            foreach ($request->cobsr as $rec) {
                $status = $rec->status ?? null;
                $txid   = $rec->txid ?? null;
                if ($txid) {
                    $pagamento = PagamentoPix::where('txid', $txid)->first();
                    if ($pagamento && strtoupper($status) == 'ACEITA') {
                        $pagamento->status = $status;
                        $pagamento->dataPagamento = now();
                        $pagamento->save();
                        $usuario = Usuarios::where('id', $pagamento->idUsuario)->first();
                        $plano = Planos::where('id', $usuario->idPlano)->first();
                        $usuario->status = 1;
                        $usuario->dataLimiteCompra = Carbon::now()->addDays($plano->frequenciaCobranca == 1 ? Helper::TEMPO_RENOVACAO_MENSAL : Helper::TEMPO_RENOVACAO_ANUAL)->setTimezone('America/Recife')->format('Y-m-d');
                        $usuario->dataUltimoPagamento = Carbon::now()->format('Y-m-d H:i:s');
                        $usuario->idPlano = $plano->id;
                        $usuario->save();
                    }
                }
            }
        }
        return response()->json([
            'codRetorno' => 200,
            'message' => 'Processamento do pagamento realizado com sucesso',
        ], 200);
    }

    private function executeApiRequestWithExtraHeaders(string $url, ?string $method = 'POST', ?string $body = null, array $extraHeaders = []): array
    {
        if (!file_exists($this->certificadoPath)) {
            Log::error('Certificado não encontrado', [
                'path' => $this->certificadoPath,
                'environment' => $this->enviroment
            ]);
            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado não encontrado: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }
        if (!is_readable($this->certificadoPath)) {
            Log::error('Certificado não é legível', [
                'path' => $this->certificadoPath,
                'permissions' => substr(sprintf('%o', fileperms($this->certificadoPath)), -4)
            ]);
            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado não é legível: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }
        $curl = curl_init();
        $headers = [
            "Authorization: Bearer " . $this->apiEfi->getToken(),
            "Content-Type: application/json"
        ];
        $headers = array_merge($headers, $extraHeaders);
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_REQUEST,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_SSLCERTTYPE => "PEM",
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($this->enviroment === 'local') {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        } else {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
        }
        if ($body) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $curlOptions);
        $startTime = microtime(true);
        $response = curl_exec($curl);
        $executionTime = microtime(true) - $startTime;
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($curl);
        $error = curl_error($curl);
        curl_close($curl);
        $decodedResponse = null;
        if ($response) {
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Erro ao decodificar JSON da resposta da API (extra headers)', [
                    'json_error' => json_last_error_msg(),
                    'response_length' => strlen($response),
                    'response_preview' => substr($response, 0, 500),
                    'url' => $url
                ]);
            }
        }
        return [
            'success' => !$error && ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'error' => $error,
            'data' => $decodedResponse,
            'raw_response' => $response,
            'url' => $url,
            'body' => $body,
            'execution_time' => $executionTime,
            'curl_info' => $curlInfo
        ];
    }

    public function consultarWebhookRecorrente(): JsonResponse
    {
        try {
            $url = $this->buildApiUrl('/v2/webhookrec');
            $headers = [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ];
            $response = $this->executeApiRequestWithExtraHeaders($url, 'GET', null, $headers);
            if ($response['success'] && isset($response['data']['webhookUrl'])) {
                return response()->json([
                    'webhookUrl' => $response['data']['webhookUrl'],
                    'criacao' => $response['data']['criacao'] ?? null
                ], 200);
            }
            $httpCode = $response['http_code'] ?? 503;
            $errorMsg = $response['error'] ?? 'Erro ao consultar webhook de recorrência';
            return response()->json([
                'error' => $errorMsg,
                'data' => $response['data'] ?? null
            ], $httpCode);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 503);
        }
    }
}
