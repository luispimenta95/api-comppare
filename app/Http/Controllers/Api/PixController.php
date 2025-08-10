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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Util\Helper;

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

    /**
     * Inicializa o caminho do certificado baseado no ambiente
     */
    private function initializeCertificadoPath(): void
    {
        $this->certificadoPath = $this->enviroment == "local"
            ? storage_path('app/certificates/hml.pem')
            : storage_path('app/certificates/prd.pem');
    }

    /**
     * Inicializa a chave PIX baseada no ambiente
     */
    private function initializeChavePix(): void
    {
        $this->chavePix = env('CHAVE_PIX');
    }

    /**
     * Fluxo completo de criação de cobrança PIX recorrente: COB → LOCREC → REC → QRCODE
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function criarCobranca(Request $request): JsonResponse
    {
        try {
            // Validar e inicializar dados
            $this->initializeRequestData($request);

            // Executar fluxo PIX recorrente
            $pixData = $this->executarFluxoPixRecorrente();

            $this->salvarPagamentoPix($pixData);

            // Enviar email
            $this->enviarEmailPix($pixData['pixCopiaECola'], $pixData['txid']);

            return $this->buildSuccessResponse($pixData);
        } catch (\Exception $e) {
            Log::error('Erro geral no fluxo PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno no processamento PIX',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inicializa os dados da requisição
     */
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

    /**
     * Gera número de contrato único
     */
    private function generateNumeroContrato(): string
    {
        return strval(mt_rand(10000000, 99999999));
    }

    /**
     * Determina a frequência baseada no plano
     */
    private function determineFrequencia(): string
    {
        return $this->plano->frequenciaCobranca == 12
            ? Helper::PERIODICIDADE_ANUAL
            : Helper::PERIODICIDADE_MENSAL;
    }

    /**
     * Executa o fluxo completo PIX recorrente
     */
    private function executarFluxoPixRecorrente(): array
    {
        // Passo 1: Definir TXID
        $txid = $this->definirTxid();

        // Passo 2: Criar COB
        $cobResponse = $this->criarCob($txid);
        $this->validateResponse($cobResponse, 'COB');

        // Passo 3: Criar Location Rec
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

        // Passo 4: Criar REC
        $recResponse = $this->criarRec($txid, $locrecId);
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

        // Passo 5: Resgatar QR Code
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
            throw new \RuntimeException('Código PIX não gerado por falta de dados');
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

    /**
     * Valida resposta das APIs
     */
    private function validateResponse(array $response, string $step): void
    {
        // Log detalhado para cada passo
        Log::info("Validando resposta da API - Passo {$step}", [
            'step' => $step,
            'success' => $response['success'] ?? false,
            'http_code' => $response['http_code'] ?? null,
            'has_error' => !empty($response['error']),
            'error' => $response['error'] ?? null,
            'has_data' => isset($response['data']),
            'data_keys' => isset($response['data']) ? array_keys($response['data']) : [],
            'url' => $response['url'] ?? null,
            'environment' => $this->enviroment
        ]);

        if (!$response['success']) {
            // Logs específicos para diferentes tipos de erro
            $errorDetails = [
                'step' => $step,
                'response' => $response,
                'http_code' => $response['http_code'] ?? null,
                'error' => $response['error'] ?? 'Erro desconhecido',
                'url' => $response['url'] ?? null,
                'body' => $response['body'] ?? null,
                'usuario_id' => $this->usuario->id ?? null,
                'plano_id' => $this->plano->id ?? null,
                'environment' => $this->enviroment
            ];

            // Análise específica por código HTTP
            if (isset($response['http_code'])) {
                switch ($response['http_code']) {
                    case 400:
                        $errorDetails['error_type'] = 'BAD_REQUEST';
                        $errorDetails['suggestion'] = 'Verificar dados enviados na requisição';
                        break;
                    case 401:
                        $errorDetails['error_type'] = 'UNAUTHORIZED';
                        $errorDetails['suggestion'] = 'Verificar token de autenticação';
                        break;
                    case 403:
                        $errorDetails['error_type'] = 'FORBIDDEN';
                        $errorDetails['suggestion'] = 'Verificar permissões e certificados';
                        break;
                    case 404:
                        $errorDetails['error_type'] = 'NOT_FOUND';
                        $errorDetails['suggestion'] = 'Verificar URL da API e endpoints';
                        break;
                    case 422:
                        $errorDetails['error_type'] = 'UNPROCESSABLE_ENTITY';
                        $errorDetails['suggestion'] = 'Verificar validação dos dados enviados';
                        break;
                    case 500:
                        $errorDetails['error_type'] = 'INTERNAL_SERVER_ERROR';
                        $errorDetails['suggestion'] = 'Erro no servidor da API EFI';
                        break;
                    default:
                        $errorDetails['error_type'] = 'UNKNOWN_HTTP_ERROR';
                        $errorDetails['suggestion'] = 'Verificar documentação da API EFI';
                }
            }

            // Tentar analisar response body para mais detalhes
            if (isset($response['data']) && is_array($response['data'])) {
                $errorDetails['api_error_details'] = $response['data'];

                // Extrair mensagens de erro específicas da API EFI
                if (isset($response['data']['error'])) {
                    $errorDetails['api_error_message'] = $response['data']['error'];
                }
                if (isset($response['data']['error_description'])) {
                    $errorDetails['api_error_description'] = $response['data']['error_description'];
                }
                if (isset($response['data']['violacoes'])) {
                    $errorDetails['api_violations'] = $response['data']['violacoes'];
                }
            }

            Log::error("Erro na validação da resposta da API - Passo {$step}", $errorDetails);

            // Criar mensagem de erro mais específica
            $errorMessage = "Erro no passo {$step}";
            if (isset($errorDetails['api_error_message'])) {
                $errorMessage .= ": " . $errorDetails['api_error_message'];
            } elseif (isset($response['error']) && !empty($response['error'])) {
                $errorMessage .= ": " . $response['error'];
            } else {
                $errorMessage .= ": Erro desconhecido (HTTP " . ($response['http_code'] ?? 'N/A') . ")";
            }

            throw new \RuntimeException($errorMessage);
        }

        // Log de sucesso também é útil para debug
        Log::info("Passo {$step} executado com sucesso", [
            'step' => $step,
            'http_code' => $response['http_code'] ?? null,
            'data_keys' => isset($response['data']) ? array_keys($response['data']) : []
        ]);
    }

    /**
     * Salva pagamento PIX no banco de dados
     */
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

            Log::info('Pagamento PIX salvo no banco', ['id' => $pagamentoPix->id]);
            return $pagamentoPix;
        } catch (\Exception $e) {
            Log::error('Erro ao salvar pagamento PIX', [
                'error' => $e->getMessage(),
                'txid' => $pixData['txid']
            ]);
            throw $e;
        }
    }

    /**
     * Envia email com código PIX
     */
    private function enviarEmailPix(string $pixCopiaECola, string $txid): void
    {
        try {
            Log::info('Iniciando envio de email PIX', [
                'email' => $this->usuario->email,
                'txid' => $txid,
                'nome' => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome
            ]);

            $dadosParaEmail = $this->buildEmailData($pixCopiaECola, $txid);

            Mail::to($this->usuario->email)->send(new EmailPix($dadosParaEmail));

            Log::info('Email PIX enviado com sucesso', [
                'email' => $this->usuario->email,
                'txid' => $txid
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email PIX', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'email_destino' => $this->usuario->email,
                'txid' => $txid
            ]);
            // Não lança exceção para não interromper o fluxo
        }
    }

    /**
     * Constrói dados para o email
     */
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

    /**
     * Constrói resposta de sucesso
     */
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

    /**
     * Passo 1: Definir um TXID único
     */
    private function definirTxid(): string
    {
        return md5(uniqid(rand(), true));
    }

    /**
     * Passo 2: Criar COB - PUT /v2/cob/:txid
     */
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

    /**
     * Passo 3: Criar Location Rec - POST /v2/locrec
     */
    private function criarLocationRec(): array
    {
        $url = $this->buildApiUrl("/v2/locrec");
        return $this->executeApiRequest($url);
    }

    /**
     * Passo 4: Criar REC - POST /v2/rec
     */
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

        // Log detalhado antes da requisição REC
        Log::info('Iniciando criação REC', [
            'txid' => $txid,
            'locrecId' => $locrecId,
            'url' => $url,
            'body_data' => json_decode($body, true),
            'usuario_id' => $this->usuario->id,
            'plano_id' => $this->plano->id,
            'environment' => $this->enviroment
        ]);

        $response = $this->executeApiRequest($url, null, $body);

        // Log detalhado da resposta REC
        Log::info('Resposta da criação REC', [
            'txid' => $txid,
            'locrecId' => $locrecId,
            'response' => $response,
            'success' => $response['success'],
            'http_code' => $response['http_code'],
            'has_data' => isset($response['data']),
            'data_content' => $response['data'] ?? null
        ]);

        return $response;
    }

    /**
     * Passo 5: Resgatar QR Code - GET /v2/rec/{idRec}?txid={txid}
     */
    private function resgatarQRCode(string $recId, string $txid): array
    {
        $url = $this->buildApiUrl("/v2/rec/{$recId}?txid={$txid}");
        return $this->executeApiRequest($url, 'GET');
    }

    /**
     * Constrói URL da API baseada no ambiente
     */
    private function buildApiUrl(string $endpoint): string
    {
        // Adicione as variáveis abaixo no seu arquivo .env:


        $baseUrl = $this->enviroment === 'local'
            ? env('URL_API_PIX_LOCAL')
            : env('URL_API_PIX_PRODUCAO');

        return $baseUrl . $endpoint;
    }

    /**
     * Executa requisição para API EFI
     */
    private function executeApiRequest(string $url, ?string $method = 'POST', ?string $body = null): array
    {
        // Validar certificado antes de fazer a requisição
        if (!file_exists($this->certificadoPath)) {
            Log::error('Certificado EFI não encontrado', [
                'certificate_path' => $this->certificadoPath,
                'environment' => $this->enviroment
            ]);

            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado EFI não encontrado: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }

        if (!is_readable($this->certificadoPath)) {
            Log::error('Certificado EFI não pode ser lido', [
                'certificate_path' => $this->certificadoPath,
                'environment' => $this->enviroment
            ]);

            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado EFI não pode ser lido: {$this->certificadoPath}",
                'data' => null,
                'url' => $url,
                'body' => $body
            ];
        }

        if (filesize($this->certificadoPath) === 0) {
            Log::error('Certificado EFI está vazio', [
                'certificate_path' => $this->certificadoPath,
                'environment' => $this->enviroment
            ]);

            return [
                'success' => false,
                'http_code' => 0,
                'error' => "Certificado EFI está vazio: {$this->certificadoPath}",
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
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
            // Opções SSL/TLS para ambiente local
            CURLOPT_SSL_VERIFYHOST => $this->enviroment === 'local' ? 0 : 2,
            CURLOPT_SSL_VERIFYPEER => $this->enviroment === 'local' ? false : true,
            CURLOPT_VERBOSE => false, // Desativar verbose para reduzir logs
        ];

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
        $curlErrno = curl_errno($curl);
        curl_close($curl);

        // Log detalhado da requisição
        Log::info('Execução de requisição para API EFI', [
            'url' => $url,
            'method' => $method,
            'has_body' => !empty($body),
            'body_length' => $body ? strlen($body) : 0,
            'execution_time' => round($executionTime, 3) . 's',
            'http_code' => $httpCode,
            'has_curl_error' => !empty($error),
            'curl_errno' => $curlErrno,
            'response_size' => $response ? strlen($response) : 0,
            'ssl_verify_result' => $curlInfo['ssl_verify_result'] ?? null,
            'cert_info' => [
                'exists' => file_exists($this->certificadoPath),
                'path' => $this->certificadoPath,
                'readable' => is_readable($this->certificadoPath),
                'size' => file_exists($this->certificadoPath) ? filesize($this->certificadoPath) : 0
            ]
        ]);

        // Se houve erro de cURL, logar detalhes adicionais
        if (!empty($error)) {
            $errorDetails = [
                'curl_error' => $error,
                'curl_errno' => $curlErrno,
                'url' => $url,
                'method' => $method,
                'environment' => $this->enviroment
            ];

            // Análise específica de erros de certificado
            if (strpos($error, 'certificate') !== false || $curlErrno === CURLE_SSL_CERTPROBLEM) {
                $errorDetails['analysis'] = 'Problema com certificado SSL/TLS';
                $errorDetails['suggestions'] = [
                    'Verificar se o certificado existe e pode ser lido',
                    'Verificar se o certificado não está expirado',
                    'Para ambiente local, considerar desabilitar verificação SSL'
                ];
            }

            Log::error('Erro de cURL na requisição para API EFI', $errorDetails);
        }

        // Se response vazio mas sem erro de cURL, pode ser problema de rede/SSL
        if (empty($response) && empty($error) && $httpCode === 0) {
            Log::warning('Resposta vazia sem erro de cURL - possível problema de conectividade', [
                'url' => $url,
                'curl_info' => $curlInfo,
                'certificate_path' => $this->certificadoPath,
                'certificate_exists' => file_exists($this->certificadoPath)
            ]);
        }

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
            'curl_errno' => $curlErrno,
            'data' => $decodedResponse,
            'raw_response' => $response,
            'url' => $url,
            'body' => $body,
            'execution_time' => $executionTime,
            'curl_info' => $curlInfo
        ];
    }

    /**
     * Atualiza cobrança PIX via webhook (com validação mTLS da EFI)
     * Endpoint: POST /api/pix/atualizar
     */
    public function atualizarCobranca(Request $request): JsonResponse
    {
        Log::info(
            'Webhook PIX recebido - Início do processamento'
        );
        die;
        try {
            // Log da requisição recebida
            Log::info('Webhook PIX recebido', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Validar autenticação mTLS da EFI
            if (!$this->validarCertificadoEfi($request)) {
                Log::warning('Tentativa de acesso ao webhook sem certificado EFI válido', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'status' => 403,
                    'mensagem' => 'Acesso negado. Certificado EFI inválido.',
                    'dados' => null
                ], 403);
            }

            $recs = $request->input('recs', []);
            $resultados = [];

            if (!is_array($recs) || empty($recs)) {
                return response()->json([
                    'status' => 200,
                    'mensagem' => 'Requisição realizada com sucesso!',
                    'dados' => 'Nenhum REC fornecido para atualização'
                ]);
            }

            foreach ($recs as $rec) {
                if (!isset($rec['idRec']) || !isset($rec['status'])) {
                    continue;
                }

                $idRec = $rec['idRec'];
                $status = $rec['status'];

                $pagamentoPix = PagamentoPix::where('recId', $idRec)->first();

                if ($pagamentoPix) {
                    $statusAnterior = $pagamentoPix->status;
                    $pagamentoPix->status = $status;
                    $pagamentoPix->dataPagamento = now();
                    $pagamentoPix->save();

                    Log::info('Status da cobrança PIX atualizado', [
                        'idRec' => $idRec,
                        'status_anterior' => $statusAnterior,
                        'status_novo' => $status
                    ]);

                    $resultados[] = [
                        'idRec' => $idRec,
                        'status' => $status,
                        'atualizado' => true
                    ];

                    // Atualizar usuário se pagamento foi aprovado
                    if ($status === 'APROVADA') {
                        $usuario = Usuarios::find($pagamentoPix->idUsuario);
                        if ($usuario) {
                            $usuario->status = 1;
                            $usuario->dataUltimoPagamento = now();
                            $usuario->idUltimaCobranca = $idRec;
                            $usuario->save();
                        }
                    }
                } else {
                    $resultados[] = [
                        'idRec' => $idRec,
                        'status' => $status,
                        'atualizado' => false,
                        'motivo' => 'Pagamento não encontrado'
                    ];
                }
            }

            return response()->json([
                'status' => 200,
                'mensagem' => 'Requisição realizada com sucesso!',
                'dados' => $resultados
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook PIX', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 400,
                'mensagem' => $e->getMessage(),
                'dados' => null
            ], 400);
        }
    }

    /**
     * Configura webhook PIX com suporte a mTLS ou skip-mTLS
     * PUT /api/pix/webhook
     */
    public function configurarWebhook(Request $request): JsonResponse
    {
        try {
            $webhookUrl = $request->input('webhookUrl') ?: env('WEBHOOK_PIX_URL');
            $skipMtls = $request->input('skip_mtls', true); // ALTERADO: padrão é TRUE

            if (!$webhookUrl) {
                // Usar endpoint principal conforme especificação EFI
                $webhookUrl = env('APP_URL') . '/api/pix';
            }

            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'codRetorno' => 400,
                    'message' => 'URL do webhook inválida',
                    'webhookUrl' => $webhookUrl
                ], 400);
            }

            Log::info('Configurando webhook PIX', [
                'webhook_url' => $webhookUrl,
                'skip_mtls' => $skipMtls,
                'environment' => $this->enviroment
            ]);

            // Fazer requisição para API EFI
            $url = $this->buildApiUrl("/v2/webhook/{$this->chavePix}");

            $headers = [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ];

            // Adicionar header skip-mTLS se necessário
            if ($skipMtls) {
                $headers[] = "x-skip-mtls-checking: true";
            }

            $body = json_encode([
                "webhookUrl" => $webhookUrl
            ]);

            $curl = curl_init();

            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT_REQUEST,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLCERT => $this->certificadoPath,
                CURLOPT_SSLCERTPASSWD => "",
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ];

            curl_setopt_array($curl, $curlOptions);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('Erro cURL ao configurar webhook', [
                    'error' => $error,
                    'url' => $url
                ]);

                return response()->json([
                    'codRetorno' => 500,
                    'message' => 'Erro de conectividade',
                    'error' => $error
                ], 500);
            }

            $responseData = $response ? json_decode($response, true) : null;

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('Webhook PIX configurado com sucesso', [
                    'webhook_url' => $webhookUrl,
                    'skip_mtls' => $skipMtls,
                    'response' => $responseData
                ]);

                return response()->json([
                    'codRetorno' => 200,
                    'message' => 'Webhook configurado com sucesso',
                    'data' => [
                        'webhookUrl' => $webhookUrl,
                        'skip_mtls' => $skipMtls,
                        'mtls_info' => $skipMtls ?
                            'mTLS desabilitado - validação manual necessária' :
                            'mTLS habilitado - certificado EFI será validado automaticamente',
                        'configurado_em' => now()->toDateTimeString(),
                        'response' => $responseData
                    ]
                ]);
            } else {
                Log::error('Erro HTTP ao configurar webhook', [
                    'http_code' => $httpCode,
                    'response' => $responseData,
                    'url' => $url,
                    'headers' => $headers
                ]);

                // Análise do erro específico
                $errorAnalysis = $this->analisarErroWebhook($httpCode, $responseData);

                return response()->json([
                    'codRetorno' => $httpCode,
                    'message' => 'Erro ao configurar webhook na EFI',
                    'error' => $responseData['mensagem'] ?? $responseData['detail'] ?? 'Erro desconhecido',
                    'detalhes' => $responseData,
                    'analise' => $errorAnalysis,
                    'solucao_recomendada' => $errorAnalysis['solucao'] ?? 'Verificar logs da EFI'
                ], $httpCode >= 400 ? $httpCode : 500);
            }
        } catch (\Exception $e) {
            Log::error('Erro geral ao configurar webhook PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao configurar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configura webhook automaticamente com skip-mTLS
     * GET /api/pix/configurar-webhook-skip-mtls
     */
    public function configurarWebhookSkipMtls(Request $request): JsonResponse
    {
        try {
            $webhookUrl = $request->input('webhookUrl') ?: env('WEBHOOK_PIX_URL') ?: env('APP_URL') . '/api/pix';

            Log::info('Configurando webhook PIX com skip-mTLS automático', [
                'webhook_url' => $webhookUrl,
                'environment' => $this->enviroment
            ]);

            // Fazer requisição para API EFI com skip-mTLS obrigatório
            $url = $this->buildApiUrl("/v2/webhook/{$this->chavePix}");

            $headers = [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json",
                "x-skip-mtls-checking: true"  // SEMPRE usar skip-mTLS
            ];

            $body = json_encode([
                "webhookUrl" => $webhookUrl
            ]);

            $curl = curl_init();

            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT_REQUEST,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLCERT => $this->certificadoPath,
                CURLOPT_SSLCERTPASSWD => "",
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ];

            curl_setopt_array($curl, $curlOptions);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('Erro cURL ao configurar webhook com skip-mTLS', [
                    'error' => $error,
                    'url' => $url
                ]);

                return response()->json([
                    'codRetorno' => 500,
                    'message' => 'Erro de conectividade',
                    'error' => $error,
                    'solucao' => 'Verificar conectividade com API EFI e certificado cliente'
                ], 500);
            }

            $responseData = $response ? json_decode($response, true) : null;

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('Webhook PIX configurado com sucesso (skip-mTLS)', [
                    'webhook_url' => $webhookUrl,
                    'http_code' => $httpCode,
                    'response' => $responseData
                ]);

                return response()->json([
                    'codRetorno' => 200,
                    'message' => 'Webhook configurado com sucesso usando skip-mTLS',
                    'data' => [
                        'webhookUrl' => $webhookUrl,
                        'skip_mtls' => true,
                        'configurado_em' => now()->toDateTimeString(),
                        'observacao' => $httpCode < 400 ?
                            'mTLS foi ignorado - EFI não validará certificados no servidor' :
                            'Erro ao configurar webhook mesmo com skip-mTLS',
                        'próximos_passos' => [
                            '1. Testar webhook: curl -X POST ' . $webhookUrl,
                            '2. Configurar Nginx para mTLS completo (opcional)',
                            '3. Monitorar logs: tail -f storage/logs/laravel.log'
                        ],
                        'response' => $responseData
                    ]
                ]);
            } else {
                $errorAnalysis = $this->analisarErroWebhook($httpCode, $responseData);

                return response()->json([
                    'codRetorno' => $httpCode,
                    'message' => 'Erro ao configurar webhook mesmo com skip-mTLS',
                    'error' => $responseData['mensagem'] ?? $responseData['detail'] ?? 'Erro desconhecido',
                    'detalhes' => $responseData,
                    'analise' => $errorAnalysis,
                    'debug_info' => [
                        'chave_pix' => $this->chavePix,
                        'url_api' => $url,
                        'certificado_path' => $this->certificadoPath,
                        'certificado_exists' => file_exists($this->certificadoPath)
                    ]
                ], $httpCode >= 400 ? $httpCode : 500);
            }
        } catch (\Exception $e) {
            Log::error('Erro geral ao configurar webhook com skip-mTLS', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao configurar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook PIX simples que responde apenas "200" (conforme especificação EFI)
     * POST /api/pix/webhook-simple
     */
    public function webhookSimple(Request $request): Response
    {
        try {
            // Log da requisição recebida para debug
            Log::info('Webhook PIX simples recebido', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'body' => $request->all()
            ]);

            // Validar autenticação mTLS da EFI
            if (!$this->validarCertificadoEfi($request)) {
                Log::warning('Tentativa de acesso ao webhook simples sem certificado EFI válido', [
                    'ip' => $request->ip()
                ]);

                // Retornar 403 sem corpo JSON para tentar manter compatibilidade
                return response('Forbidden', 403);
            }

            // Processar dados PIX se necessário (em background ou de forma assíncrona)
            // Para não afetar a resposta rápida exigida pela EFI
            $this->processarWebhookAsync($request->all());

            // Resposta simples "200" conforme especificação EFI
            return response('200', 200);
        } catch (\Exception $e) {
            Log::error('Erro no webhook PIX simples', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            // Mesmo em caso de erro, retornar 200 para não afetar o relacionamento com EFI
            return response('200', 200);
        }
    }

    /**
     * Processa webhook de forma assíncrona (implementação simplificada)
     */
    private function processarWebhookAsync(array $data): void
    {
        // Implementação assíncrona seria através de Jobs/Queues
        // Por enquanto, só registra no log para processamento posterior
        Log::info('Dados PIX para processamento assíncrono', $data);

        // TODO: Implementar Job para processar os dados do webhook
        // dispatch(new ProcessarWebhookPixJob($data));
    }

    /**
     * Valida certificado EFI para mTLS (suporta $_SERVER e headers Nginx)
     */
    private function validarCertificadoEfi(Request $request): bool
    {
        // Verificar IP da EFI primeiro (recomendação para skip-mTLS)
        $efiIp = '34.193.116.226';
        $clientIp = $request->ip();

        if ($clientIp === $efiIp) {
            Log::info('IP da EFI reconhecido', ['ip' => $clientIp]);
            return true;
        }

        // Verificar certificado SSL/TLS via $_SERVER ou headers Nginx
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? $request->header('SSL-Client-Cert');
        $clientCertVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? $request->header('SSL-Client-Verify');

        if (!$clientCert) {
            Log::debug('Certificado cliente não encontrado', [
                'server_ssl_client_cert' => isset($_SERVER['SSL_CLIENT_CERT']) ? 'presente' : 'ausente',
                'nginx_header_cert' => $request->hasHeader('SSL-Client-Cert') ? 'presente' : 'ausente'
            ]);
            return false;
        }

        if ($clientCertVerify !== 'SUCCESS') {
            Log::warning('Certificado cliente falhou na verificação', [
                'ssl_client_verify' => $clientCertVerify,
                'client_ip' => $clientIp
            ]);
            return false;
        }

        // Parse do certificado para validar se é da EFI
        try {
            // Remover headers PEM se presentes
            $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $clientCert);
            $cleanCert = str_replace(["\n", "\r", " "], '', $cleanCert);
            $cleanCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cleanCert, 64, "\n") . "-----END CERTIFICATE-----";

            $certInfo = openssl_x509_parse($cleanCert);

            if (!$certInfo) {
                Log::warning('Falha ao fazer parse do certificado cliente');
                return false;
            }

            $subject = $certInfo['subject'] ?? [];
            $issuer = $certInfo['issuer'] ?? [];

            $commonName = $subject['CN'] ?? '';
            $organization = $subject['O'] ?? '';

            // Verificar se é certificado da EFI
            $efiDomains = ['efipay.com.br', 'gerencianet.com.br', 'efi.com.br', 'pix.bcb.gov.br'];
            $efiOrgs = ['EFI Pay', 'Gerencianet', 'EFI S.A.', 'EFI', 'Banco Central do Brasil'];

            foreach ($efiDomains as $domain) {
                if (strpos(strtolower($commonName), strtolower($domain)) !== false) {
                    Log::info('Certificado EFI válido por domínio', [
                        'common_name' => $commonName,
                        'domain' => $domain,
                        'client_ip' => $clientIp
                    ]);
                    return true;
                }
            }

            foreach ($efiOrgs as $org) {
                if (strpos($organization, $org) !== false) {
                    Log::info('Certificado EFI válido por organização', [
                        'organization' => $organization,
                        'org' => $org,
                        'client_ip' => $clientIp
                    ]);
                    return true;
                }
            }

            Log::warning('Certificado não reconhecido como EFI', [
                'subject' => $subject,
                'issuer' => $issuer,
                'client_ip' => $clientIp
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Erro ao validar certificado EFI', [
                'error' => $e->getMessage(),
                'client_ip' => $clientIp
            ]);
            return false;
        }
    }

    /**
     * Endpoint de teste para validar TLS mútuo
     * GET /api/pix/test-tls
     */
    public function testTlsMutual(Request $request): JsonResponse
    {
        $sslInfo = [
            'ssl_client_cert' => !empty($_SERVER['SSL_CLIENT_CERT']) ? 'presente' : 'ausente',
            'ssl_client_verify' => $_SERVER['SSL_CLIENT_VERIFY'] ?? 'não verificado',
            'ssl_client_subject' => $_SERVER['SSL_CLIENT_S_DN'] ?? 'não disponível',
            'ssl_client_issuer' => $_SERVER['SSL_CLIENT_I_DN'] ?? 'não disponível',
            'ssl_protocol' => $_SERVER['SSL_PROTOCOL'] ?? 'não disponível',
            'ssl_cipher' => $_SERVER['SSL_CIPHER'] ?? 'não disponível',
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all()
        ];

        $mtlsValid = $this->validarCertificadoEfi($request);

        return response()->json([
            'status' => 200,
            'mensagem' => 'Teste TLS mútuo executado',
            'dados' => [
                'mtls_valido' => $mtlsValid,
                'ssl_info' => $sslInfo,
                'observacao' => $mtlsValid ?
                    'Certificado EFI válido ou IP reconhecido' :
                    'Certificado inválido - acesso seria negado',
                'configuracao_nginx' => 'Verifique se ssl_verify_client está configurado',
                'efi_ip_oficial' => '34.193.116.226'
            ]
        ]);
    }

    /**
     * Verifica status SSL e certificados
     * GET /api/pix/ssl-status
     */
    public function sslStatus(Request $request): JsonResponse
    {
        $certificates = [
            'efi_homologacao' => [
                'path' => storage_path('app/certificates/hml.pem'),
                'exists' => file_exists(storage_path('app/certificates/hml.pem')),
                'readable' => is_readable(storage_path('app/certificates/hml.pem'))
            ],
            'efi_producao' => [
                'path' => storage_path('app/certificates/prd.pem'),
                'exists' => file_exists(storage_path('app/certificates/prd.pem')),
                'readable' => is_readable(storage_path('app/certificates/prd.pem'))
            ],
            'server_cert' => [
                'path' => storage_path('app/certificates/server.pem'),
                'exists' => file_exists(storage_path('app/certificates/server.pem')),
                'readable' => is_readable(storage_path('app/certificates/server.pem'))
            ],
            'cliente_efi' => [
                'path' => storage_path('app/certificates/cliente-efi.pem'),
                'exists' => file_exists(storage_path('app/certificates/cliente-efi.pem')),
                'readable' => is_readable(storage_path('app/certificates/cliente-efi.pem'))
            ]
        ];

        $sslServerInfo = [
            'ssl_client_cert' => !empty($_SERVER['SSL_CLIENT_CERT']) ? 'presente' : 'ausente',
            'ssl_client_verify' => $_SERVER['SSL_CLIENT_VERIFY'] ?? 'não verificado',
            'ssl_client_subject' => $_SERVER['SSL_CLIENT_S_DN'] ?? 'não disponível',
            'ssl_client_issuer' => $_SERVER['SSL_CLIENT_I_DN'] ?? 'não disponível',
            'https' => $request->isSecure() ? 'sim' : 'não',
            'client_ip' => $request->ip()
        ];

        $envConfig = [
            'environment' => $this->enviroment,
            'webhook_pix_url' => env('WEBHOOK_PIX_URL'),
            'app_url' => env('APP_URL'),
            'chave_pix' => !empty($this->chavePix) ? 'configurada' : 'não configurada',
            'url_api_pix_local' => env('URL_API_PIX_LOCAL'),
            'url_api_pix_producao' => env('URL_API_PIX_PRODUCAO')
        ];

        return response()->json([
            'status' => 200,
            'mensagem' => 'Status SSL e certificados',
            'dados' => [
                'certificates' => $certificates,
                'ssl_server_info' => $sslServerInfo,
                'environment_config' => $envConfig,
                'nginx_headers_received' => [
                    'ssl_client_cert' => $request->header('SSL-Client-Cert') ? 'presente' : 'ausente',
                    'ssl_client_verify' => $request->header('SSL-Client-Verify') ?? 'não recebido',
                    'ssl_client_subject_dn' => $request->header('SSL-Client-Subject-DN') ?? 'não recebido',
                    'ssl_client_issuer_dn' => $request->header('SSL-Client-Issuer-DN') ?? 'não recebido'
                ],
                'mtls_validation' => $this->validarCertificadoEfi($request)
            ]
        ]);
    }

    /**
     * Verifica status do webhook e configuração PIX
     * GET /api/pix/webhook-status
     */
    public function webhookStatus(Request $request): JsonResponse
    {
        $certificates = [
            'efi_homologacao' => file_exists(storage_path('app/certificates/hml.pem')) ? 'presente' : 'ausente',
            'efi_producao' => file_exists(storage_path('app/certificates/prd.pem')) ? 'presente' : 'ausente',
            'server_cert' => file_exists(storage_path('app/certificates/server.pem')) ? 'presente' : 'ausente',
            'cliente_efi' => file_exists(storage_path('app/certificates/cliente-efi.pem')) ? 'presente' : 'ausente'
        ];

        $webhookConfig = [
            'webhook_url_principal' => env('WEBHOOK_PIX_URL') ?: env('APP_URL') . '/api/pix',
            'webhook_url_alternativo' => env('APP_URL') . '/api/pix/atualizar',
            'webhook_url_simples' => env('APP_URL') . '/api/pix/webhook-simple',
            'environment' => $this->enviroment,
            'chave_pix' => !empty($this->chavePix) ? 'configurada' : 'não configurada',
            'certificate_path' => $this->certificadoPath,
            'certificate_exists' => file_exists($this->certificadoPath)
        ];

        $mtlsInfo = [
            'mtls_habilitado' => 'Validação automática por certificado e IP EFI',
            'skip_mtls_header' => 'Use x-skip-mtls-checking: true para desabilitar validação',
            'efi_ip_oficial' => '34.193.116.226',
            'validacao_atual' => $this->validarCertificadoEfi($request),
            'certificado_presente' => !empty($_SERVER['SSL_CLIENT_CERT']) ? 'sim' : 'não'
        ];

        return response()->json([
            'status' => 200,
            'mensagem' => 'Status do webhook PIX',
            'dados' => [
                'webhook_config' => $webhookConfig,
                'certificates' => $certificates,
                'mtls_info' => $mtlsInfo,
                'endpoints' => [
                    'webhook_principal' => env('APP_URL') . '/api/pix',
                    'webhook_detalhado' => env('APP_URL') . '/api/pix/atualizar',
                    'webhook_simples' => env('APP_URL') . '/api/pix/webhook-simple',
                    'configurar_webhook' => env('APP_URL') . '/api/pix/webhook',
                    'teste_tls' => env('APP_URL') . '/api/pix/test-tls',
                    'status_ssl' => env('APP_URL') . '/api/pix/ssl-status'
                ]
            ]
        ]);
    }

    /**
     * Testa a conectividade e configuração PIX (método para debug)
     * Endpoint: GET /api/pix/test-config
     */
    public function testPixConfig(Request $request): JsonResponse
    {
        try {
            Log::info('Iniciando teste de configuração PIX');

            // Validar dados básicos
            $this->enviroment = $request->input('environment', 'local');
            $this->initializeCertificadoPath();
            $this->initializeChavePix();

            $results = [
                'environment' => $this->enviroment,
                'certificates' => $this->testCertificates(),
                'api_connection' => $this->testApiConnection(),
                'token_generation' => $this->testTokenGeneration(),
                'timestamp' => now()->toDateTimeString()
            ];

            return response()->json([
                'status' => 200,
                'mensagem' => 'Teste de configuração PIX executado',
                'dados' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao testar configuração PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 500,
                'mensagem' => 'Erro ao testar configuração PIX',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testa certificados
     */
    private function testCertificates(): array
    {
        $results = [
            'certificado_path' => $this->certificadoPath,
            'certificado_exists' => file_exists($this->certificadoPath),
            'certificado_readable' => is_readable($this->certificadoPath),
            'certificado_size' => file_exists($this->certificadoPath) ? filesize($this->certificadoPath) : 0
        ];

        if (file_exists($this->certificadoPath)) {
            // Tentar ler informações do certificado
            try {
                $certData = openssl_x509_parse(file_get_contents($this->certificadoPath));
                $results['certificado_info'] = [
                    'subject' => $certData['subject'] ?? null,
                    'issuer' => $certData['issuer'] ?? null,
                    'valid_from' => isset($certData['validFrom_time_t']) ? date('Y-m-d H:i:s', $certData['validFrom_time_t']) : null,
                    'valid_to' => isset($certData['validTo_time_t']) ? date('Y-m-d H:i:s', $certData['validTo_time_t']) : null,
                    'is_expired' => isset($certData['validTo_time_t']) ? time() > $certData['validTo_time_t'] : null
                ];
            } catch (\Exception $e) {
                $results['certificado_error'] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Testa conexão com a API
     */
    private function testApiConnection(): array
    {
        try {
            $baseUrl = $this->enviroment === 'local'
                ? env('URL_API_PIX_LOCAL')
                : env('URL_API_PIX_PRODUCAO');

            $results = [
                'base_url' => $baseUrl,
                'url_configured' => !empty($baseUrl)
            ];

            if (empty($baseUrl)) {
                $results['error'] = 'URL da API não configurada no .env';
                return $results;
            }

            // Teste simples de conectividade
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $baseUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_SSL_VERIFYPEER => false, // Para teste inicial
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            $results['connectivity_test'] = [
                'http_code' => $httpCode,
                'curl_error' => $error,
                'can_connect' => empty($error) && $httpCode > 0
            ];

            return $results;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'base_url' => $baseUrl ?? null
            ];
        }
    }

    /**
     * Testa geração de token
     */
    private function testTokenGeneration(): array
    {
        try {
            // Criar instância da ApiEfi usando a classe do projeto
            $apiEfi = new \App\Http\Util\Payments\ApiEfi();

            $token = $apiEfi->getToken();

            return [
                'client_id_configured' => !empty(env('ID_EFI_HML')) || !empty(env('ID_EFI_PRD')),
                'client_secret_configured' => !empty(env('SECRET_EFI_HML')) || !empty(env('SECRET_EFI_PRD')),
                'token_generated' => !empty($token),
                'token_length' => strlen($token ?? ''),
                'api_instance_created' => true,
                'environment' => $this->enviroment
            ];
        } catch (\Exception $e) {
            return [
                'client_id_configured' => !empty(env('ID_EFI_HML')) || !empty(env('ID_EFI_PRD')),
                'client_secret_configured' => !empty(env('SECRET_EFI_HML')) || !empty(env('SECRET_EFI_PRD')),
                'token_generated' => false,
                'error' => $e->getMessage(),
                'api_instance_created' => false,
                'environment' => $this->enviroment
            ];
        }
    }

    /**
     * Cria certificado de teste para ambiente local (método auxiliar para desenvolvimento)
     * Endpoint: POST /api/pix/create-test-certificate
     */
    public function createTestCertificate(Request $request): JsonResponse
    {
        try {
            // Permitir em ambiente local ou se forçado via parâmetro
            $forceCreate = $request->input('force', false);

            if ($this->enviroment !== 'local' && !$forceCreate) {
                return response()->json([
                    'status' => 403,
                    'mensagem' => 'Este endpoint só está disponível em ambiente local. Use force=true se necessário.',
                    'environment' => $this->enviroment,
                    'suggestion' => 'Adicione {"force": true} no body da requisição para forçar criação'
                ], 403);
            }

            $certificateDir = storage_path('app/certificates');

            // Criar diretório se não existir
            if (!is_dir($certificateDir)) {
                mkdir($certificateDir, 0755, true);
                Log::info('Diretório de certificados criado', ['path' => $certificateDir]);
            }

            $certPath = storage_path('app/certificates/hml.pem');

            // Verificar se já existe
            if (file_exists($certPath)) {
                $fileSize = filesize($certPath);
                if ($fileSize > 0) {
                    return response()->json([
                        'status' => 200,
                        'mensagem' => 'Certificado já existe e não está vazio',
                        'dados' => [
                            'certificate_path' => $certPath,
                            'certificate_size' => $fileSize,
                            'certificate_readable' => is_readable($certPath),
                            'action' => 'nenhuma - certificado já configurado'
                        ]
                    ]);
                }
            }

            // Criar certificado auto-assinado para teste
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];

            // Criar chave privada
            $privateKey = openssl_pkey_new($config);

            // Criar CSR (Certificate Signing Request)
            $dn = [
                "countryName" => "BR",
                "stateOrProvinceName" => "State",
                "localityName" => "City",
                "organizationName" => "Test Organization",
                "organizationalUnitName" => "IT Department",
                "commonName" => "localhost",
                "emailAddress" => "test@example.com"
            ];

            $csr = openssl_csr_new($dn, $privateKey, $config);

            // Criar certificado auto-assinado
            $x509 = openssl_csr_sign($csr, null, $privateKey, 365, $config);

            // Exportar certificado e chave privada
            openssl_x509_export($x509, $certOut);
            openssl_pkey_export($privateKey, $keyOut);

            // Combinar certificado e chave privada em um arquivo PEM
            $pemContent = $certOut . $keyOut;

            // Salvar no arquivo
            $result = file_put_contents($certPath, $pemContent);

            if ($result === false) {
                throw new \RuntimeException('Falha ao salvar certificado no arquivo');
            }

            // Definir permissões apropriadas
            chmod($certPath, 0600);

            Log::info('Certificado de teste criado com sucesso', [
                'certificate_path' => $certPath,
                'certificate_size' => filesize($certPath)
            ]);

            return response()->json([
                'status' => 200,
                'mensagem' => 'Certificado de teste criado com sucesso',
                'dados' => [
                    'certificate_path' => $certPath,
                    'certificate_size' => filesize($certPath),
                    'certificate_readable' => is_readable($certPath),
                    'action' => 'certificado auto-assinado criado para desenvolvimento',
                    'warning' => 'Este é um certificado de TESTE. Para produção, use o certificado fornecido pela EFI.',
                    'next_steps' => [
                        '1. Configure as variáveis de ambiente necessárias no .env',
                        '2. Teste a conectividade com: GET /api/pix/test-config',
                        '3. Para produção, substitua por certificado oficial da EFI'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar certificado de teste', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 500,
                'mensagem' => 'Erro ao criar certificado de teste',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analisa erros específicos do webhook para dar soluções direcionadas
     */
    private function analisarErroWebhook(int $httpCode, ?array $responseData): array
    {
        $errorName = $responseData['nome'] ?? $responseData['error'] ?? '';
        $errorMessage = $responseData['mensagem'] ?? $responseData['detail'] ?? '';

        switch ($errorName) {
            case 'webhook_invalido':
                if (strpos($errorMessage, 'autenticação de TLS mútuo') !== false) {
                    return [
                        'problema' => 'EFI não conseguiu validar mTLS no seu servidor',
                        'causa' => 'Servidor não tem mTLS configurado ou certificado EFI não está instalado',
                        'solucao' => 'Use skip_mtls: true na requisição ou configure mTLS no servidor',
                        'comando_teste' => 'curl -X PUT localhost:8000/api/pix/webhook -d \'{"skip_mtls": true}\''
                    ];
                }
                break;

            case 'webhook_url_invalida':
                return [
                    'problema' => 'URL do webhook é inválida ou inacessível',
                    'causa' => 'URL não responde ou não é HTTPS válido',
                    'solucao' => 'Verifique se a URL é acessível publicamente via HTTPS'
                ];

            case 'chave_invalida':
                return [
                    'problema' => 'Chave PIX inválida ou não encontrada',
                    'causa' => 'CHAVE_PIX no .env está incorreta',
                    'solucao' => 'Verificar se CHAVE_PIX corresponde a uma chave cadastrada na EFI'
                ];
        }

        if ($httpCode === 400) {
            return [
                'problema' => 'Dados da requisição inválidos',
                'causa' => 'Parâmetros incorretos ou chave PIX inválida',
                'solucao' => 'Verificar CHAVE_PIX e URL do webhook'
            ];
        }

        if ($httpCode === 401) {
            return [
                'problema' => 'Token de acesso inválido',
                'causa' => 'Certificado cliente EFI expirado ou inválido',
                'solucao' => 'Renovar certificado cliente da EFI'
            ];
        }

        if ($httpCode === 422) {
            return [
                'problema' => 'Validação de mTLS falhou',
                'causa' => 'EFI não conseguiu validar seu servidor via mTLS',
                'solucao' => 'Configurar skip_mtls: true ou instalar mTLS corretamente'
            ];
        }

        return [
            'problema' => 'Erro não identificado',
            'causa' => "HTTP $httpCode - $errorMessage",
            'solucao' => 'Verificar logs da EFI ou contatar suporte'
        ];
    }
}
