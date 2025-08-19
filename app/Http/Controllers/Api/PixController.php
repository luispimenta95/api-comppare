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
                'line' => $e->getLine()
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

        // Passo 3.5: Verificar status do COB antes de criar REC com retry robusto
        Log::info('Verificando status do COB antes de criar REC', [
            'txid' => $txid,
            'aguardando_ativacao' => true
        ]);

        $cobAtiva = $this->aguardarCobAtiva($txid);
        if (!$cobAtiva) {
            throw new \RuntimeException('COB não ficou ativa após múltiplas tentativas. Tente novamente em alguns minutos.');
        }

        Log::info('COB confirmada como ativa, prosseguindo com criação de REC', [
            'txid' => $txid
        ]);

        // Passo 4: Criar REC com retry automático
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

    /**
     * Valida resposta das APIs
     */
    private function validateResponse(array $response, string $step): void
    {
        if (!$response['success']) {
            // Extrair informações detalhadas do erro
            $errorDetails = [
                'step' => $step,
                'response' => $response,
                'http_code' => $response['http_code'] ?? null,
                'error' => $response['error'] ?? 'Erro desconhecido',
                'url' => $response['url'] ?? null,
                'body' => $response['body'] ?? null
            ];

            // Analisar erros específicos da API EFI
            $errorMessage = "Erro no passo {$step}";

            if (isset($response['data']) && is_array($response['data'])) {
                // Erros específicos do REC
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
                }
                // Outros erros da API
                elseif (isset($response['data']['detail'])) {
                    $errorMessage .= ": " . $response['data']['detail'];
                } elseif (isset($response['data']['mensagem'])) {
                    $errorMessage .= ": " . $response['data']['mensagem'];
                } elseif (isset($response['data']['error'])) {
                    $errorMessage .= ": " . $response['data']['error'];
                }
            }
            // Erro de cURL ou conectividade
            elseif (!empty($response['error'])) {
                $errorMessage .= ": " . $response['error'];
            }
            // Erro HTTP sem detalhes
            else {
                $errorMessage .= ": Erro HTTP " . ($response['http_code'] ?? 'desconhecido');
            }

            Log::error("Erro na validação da resposta da API - Passo {$step}", $errorDetails);
            throw new \RuntimeException($errorMessage);
        }
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
     * Cria REC com retry automático em caso de COB não ativa
     */
    private function criarRecComRetry(string $txid, $locrecId, int $maxTentativas = 3): array
    {
        $delays = [5, 10, 15]; // Delays entre tentativas em segundos

        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            Log::info("Tentativa {$tentativa}/{$maxTentativas} - Criando REC", [
                'txid' => $txid,
                'locrecId' => $locrecId
            ]);

            $recResponse = $this->criarRec($txid, $locrecId);

            // Se sucesso, retornar imediatamente
            if ($recResponse['success']) {
                Log::info("REC criado com sucesso na tentativa {$tentativa}", [
                    'txid' => $txid,
                    'tentativas_necessarias' => $tentativa
                ]);
                return $recResponse;
            }

            // Verificar se é erro de COB não ativa
            $isCobNaoAtiva = false;
            if (isset($recResponse['data']['violacoes'])) {
                foreach ($recResponse['data']['violacoes'] as $violacao) {
                    if (isset($violacao['razao']) && strpos($violacao['razao'], 'não está ativa') !== false) {
                        $isCobNaoAtiva = true;
                        break;
                    }
                }
            }

            // Se não é erro de COB não ativa, não tentar novamente
            if (!$isCobNaoAtiva) {
                Log::warning("Erro na criação de REC não relacionado a COB inativa, não tentando novamente", [
                    'tentativa' => $tentativa,
                    'error_response' => $recResponse
                ]);
                return $recResponse;
            }

            // Se ainda há tentativas restantes, aguardar e tentar novamente
            if ($tentativa < $maxTentativas) {
                $delay = $delays[$tentativa - 1];
                Log::info("COB ainda não está ativa, aguardando {$delay} segundos para nova tentativa", [
                    'txid' => $txid,
                    'tentativa' => $tentativa,
                    'delay' => $delay
                ]);
                sleep($delay);

                // Verificar status do COB novamente
                $cobStatus = $this->verificarStatusCob($txid);
                Log::info("Status do COB antes da próxima tentativa", [
                    'txid' => $txid,
                    'status' => $cobStatus['data']['status'] ?? 'DESCONHECIDO'
                ]);
            }
        }

        Log::error("Falha na criação de REC após {$maxTentativas} tentativas", [
            'txid' => $txid,
            'locrecId' => $locrecId,
            'ultimo_response' => $recResponse
        ]);

        return $recResponse;
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

        return $this->executeApiRequest($url, null, $body);
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
     * Aguarda o COB ficar ativo com múltiplas tentativas
     */
    private function aguardarCobAtiva(string $txid, int $maxTentativas = 5): bool
    {
        $delays = [3, 5, 8, 12, 20]; // Delays progressivos em segundos

        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            Log::info("Tentativa {$tentativa}/{$maxTentativas} - Verificando status do COB", [
                'txid' => $txid,
                'delay_anterior' => $tentativa > 1 ? $delays[$tentativa - 2] : 0
            ]);

            if ($tentativa > 1) {
                $delay = $delays[$tentativa - 2];
                Log::info("Aguardando {$delay} segundos antes da próxima verificação");
                sleep($delay);
            }

            $cobStatusResponse = $this->verificarStatusCob($txid);

            if ($cobStatusResponse['success'] && isset($cobStatusResponse['data']['status'])) {
                $status = $cobStatusResponse['data']['status'];

                Log::info("Status do COB na tentativa {$tentativa}", [
                    'txid' => $txid,
                    'status' => $status,
                    'response_completa' => $cobStatusResponse['data']
                ]);

                if ($status === 'ATIVA') {
                    Log::info("COB está ativa na tentativa {$tentativa}", [
                        'txid' => $txid,
                        'tentativas_necessarias' => $tentativa
                    ]);
                    return true;
                }

                // Se status for diferente de ATIVA, continuar tentando
                Log::warning("COB ainda não está ativa", [
                    'txid' => $txid,
                    'status_atual' => $status,
                    'tentativa' => $tentativa,
                    'tentativas_restantes' => $maxTentativas - $tentativa
                ]);
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

    /**
     * Verifica o status de um COB específico
     */
    private function verificarStatusCob(string $txid): array
    {
        $url = $this->buildApiUrl("/v2/cob/{$txid}");

        Log::info('Verificando status do COB', [
            'txid' => $txid,
            'url' => $url
        ]);

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
        // Verificar se o certificado existe
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

        // Verificar se o certificado é legível
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

        // Configurações SSL específicas para desenvolvimento
        if ($this->enviroment === 'local') {
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            Log::info('Configuração SSL relaxada para desenvolvimento');
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

        // Log detalhado da requisição
        Log::info('Execução de requisição para API EFI', [
            'url' => $url,
            'method' => $method,
            'has_body' => !empty($body),
            'body_length' => $body ? strlen($body) : 0,
            'execution_time' => round($executionTime, 3) . 's',
            'http_code' => $httpCode,
            'has_curl_error' => !empty($error),
            'curl_error' => $error,
            'response_size' => strlen($response),
            'cert_info' => [
                'exists' => file_exists($this->certificadoPath),
                'path' => $this->certificadoPath,
                'readable' => is_readable($this->certificadoPath)
            ]
        ]);

        // Se houve erro de cURL, logar detalhes adicionais
        if (!empty($error)) {
            Log::error('Erro de cURL na requisição para API EFI', [
                'curl_error' => $error,
                'curl_errno' => curl_errno($curl),
                'url' => $url,
                'method' => $method,
                'curl_info' => $curlInfo
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
            'data' => $decodedResponse,
            'raw_response' => $response,
            'url' => $url,
            'body' => $body,
            'execution_time' => $executionTime,
            'curl_info' => $curlInfo
        ];
    }


    /**
     * Endpoint interno para receber atualizações de status de pagamento Pix
     * Recebe notificações da Efí e atualiza status do pagamento
     */
    /**
     * Registra ou atualiza o webhook PIX na Efí
     * Agora registra tanto o webhook de recorrência quanto o de cobrança
     * PUT /v2/webhookrec e PUT /v2/webhookcobr
     * Os parâmetros enviados não são modificados
     * @return JsonResponse
     */
    public function registrarWebhook(): JsonResponse
    {
        try {
            $webhookUrl = env('APP_URL') . '/api/pix/atualizar';
            $body = json_encode([
                "webhookUrl" => $webhookUrl
            ]);

            // PUT /v2/webhookrec
            $urlRec = $this->buildApiUrl("/v2/webhookrec/");
            $responseRec = $this->executeApiRequestWithExtraHeaders($urlRec, 'PUT', $body, [
                "x-skip-mtls-checking: true"
            ]);

            // PUT /v2/webhookcobr
            $urlCobr = $this->buildApiUrl("/v2/webhookcobr/");
            $responseCobr = $this->executeApiRequestWithExtraHeaders($urlCobr, 'PUT', $body, [
                "x-skip-mtls-checking: true"
            ]);

            $result = [
                'webhookrec' => [
                    'success' => $responseRec['success'],
                    'data' => $responseRec['data'],
                    'http_code' => $responseRec['http_code'],
                    'error' => $responseRec['error'] ?? null
                ],
                'webhookcobr' => [
                    'success' => $responseCobr['success'],
                    'data' => $responseCobr['data'],
                    'http_code' => $responseCobr['http_code'],
                    'error' => $responseCobr['error'] ?? null
                ]
            ];

            // Se ambos sucesso, retorna 200
            if ($responseRec['success'] && $responseCobr['success']) {
                return response()->json([
                    'codRetorno' => 200,
                    'message' => 'Webhooks registrados com sucesso',
                    'result' => $result
                ], 200);
            }

            // Se algum falhar, retorna erro
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Falha ao registrar um ou ambos webhooks',
                'result' => $result
            ], 500);
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao registrar webhooks PIX', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao registrar webhooks Pix',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza o status de uma cobrança PIX
     * 
     * @return JsonResponse
     */
    /**
     * Endpoint público para receber notificações de webhook Pix
     * Sempre responde 200 para evitar redirecionamento (erro 302)
     */
    public function atualizarCobranca(Request $request): JsonResponse
    {
        // Não valida autenticação, não faz redirecionamento
        // Processa payload se existir
        if (isset($request->cobsr)) {
            foreach ($request->cobsr as $rec) {
                $status = $rec->status ?? null;
                $txid   = $rec->txid ?? null;

                if ($txid) {
                    $pagamento = PagamentoPix::where('txid', $txid)->first();

                    if ($pagamento && strtolower($status) == 'aprovada') {
                        $pagamento->status = $status;
                        $pagamento->dataPagamento = now();
                        $pagamento->save();
                        $usuario = Usuarios::where('id', $pagamento->idUsuario)->first();
                        $plano = Planos::where('id', $usuario->idPlano)->first();
                        $usuario->status = 1;
                        $usuario->dataLimiteCompra = Carbon::now()->addDays($plano->frequenciaCobranca == 1 ? Helper::TEMPO_RENOVACAO_MENSAL : Helper::TEMPO_RENOVACAO_ANUAL)->setTimezone('America/Recife')->format('Y-m-d');
                        $usuario->dataUltimoPagamento = Carbon::now()->format('Y-m-d H:i:s');
                    }
                }
            }
        }

        // Responde 200 sempre
        return response()->json([
            'codRetorno' => 200,
            'message' => 'Webhook recebido com sucesso',
        ], 200);
    }

    /**
     * Executa requisição para API EFI com cabeçalhos extras
     */
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
            Log::info('Configuração SSL relaxada para desenvolvimento');
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

        Log::info('Execução de requisição para API EFI (extra headers)', [
            'url' => $url,
            'method' => $method,
            'has_body' => !empty($body),
            'body_length' => $body ? strlen($body) : 0,
            'execution_time' => round($executionTime, 3) . 's',
            'http_code' => $httpCode,
            'has_curl_error' => !empty($error),
            'curl_error' => $error,
            'response_size' => strlen($response),
            'cert_info' => [
                'exists' => file_exists($this->certificadoPath),
                'path' => $this->certificadoPath,
                'readable' => is_readable($this->certificadoPath)
            ],
            'extra_headers' => $extraHeaders
        ]);

        if (!empty($error)) {
            Log::error('Erro de cURL na requisição para API EFI (extra headers)', [
                'curl_error' => $error,
                'curl_errno' => curl_errno($curl),
                'url' => $url,
                'method' => $method,
                'curl_info' => $curlInfo
            ]);
        }

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

    /**
     * Exibe informações do webhook de recorrência de Pix Automático
     * Endpoint: GET /v2/webhookrec
     * Requer autorização para o escopo: webhookrec.read
     * Possíveis respostas: 200, 403, 404, 503
     */
    public function consultarWebhookRecorrente(Request $request): JsonResponse
    {
        try {
            // URL da API Efí para consulta do webhook de recorrência
            $url = $this->buildApiUrl('/v2/webhookrec/' . $this->chavePix);

            // Adiciona o token de autorização (escopo webhookrec.read)
            $headers = [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ];

            // Requisição GET para Efí
            $response = $this->executeApiRequestWithExtraHeaders($url, 'GET', null, $headers);

            // Resposta de sucesso
            if ($response['success'] && isset($response['data']['webhookUrl'])) {
                return response()->json([
                    'webhookUrl' => $response['data']['webhookUrl'],
                    'criacao' => $response['data']['criacao'] ?? null
                ], 200);
            }

            Log::info('Webhook de recorrência consultado com erro', [
                'dados' => $response
            ]);

            // Erros específicos
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
