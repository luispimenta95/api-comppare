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
            Log::error("Erro na validação da resposta da API - Passo {$step}", [
                'step' => $step,
                'response' => $response,
                'http_code' => $response['http_code'] ?? null,
                'error' => $response['error'] ?? 'Erro desconhecido',
                'url' => $response['url'] ?? null,
                'body' => $response['body'] ?? null
            ]);
            throw new \RuntimeException("Erro no passo {$step}: " . ($response['error'] ?? 'Erro desconhecido'));
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
        ];

        if ($body) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'success' => !$error && ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'error' => $error,
            'data' => $response ? json_decode($response, true) : null,
            'url' => $url,
            'body' => $body
        ];
    }

    /**
     * Atualiza cobrança PIX via webhook (com validação mTLS da EFI)
     * Endpoint: POST /api/pix/atualizar
     */
    public function atualizarCobranca(Request $request): JsonResponse
    {
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
            $skipMtls = $request->input('skip_mtls', false);
            
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
                    'url' => $url
                ]);

                return response()->json([
                    'codRetorno' => $httpCode,
                    'message' => 'Erro ao configurar webhook na EFI',
                    'error' => $responseData['detail'] ?? 'Erro desconhecido',
                    'detalhes' => $responseData
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
}