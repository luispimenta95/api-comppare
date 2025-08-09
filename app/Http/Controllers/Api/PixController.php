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
    private function executeApiRequest(string $url, ?string $method = 'POST', ?string $body = null, bool $isWebhookConfig = false): array
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

        // Configurações SSL baseadas no ambiente
        $sslVerifyDisabled = config('app.ssl_verify_disabled', false) || env('SSL_VERIFY_DISABLED', false);
        
        if ($this->enviroment === 'local' || $sslVerifyDisabled) {
            // Em ambiente local ou com SSL verification desabilitada, permitir certificados auto-assinados
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            
            Log::info('SSL verification desabilitada', [
                'url' => $url,
                'environment' => $this->enviroment,
                'ssl_verify_disabled' => $sslVerifyDisabled
            ]);
        } else {
            // Em produção, manter verificação SSL ativa
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;
            
            Log::info('SSL verification ativa', [
                'url' => $url,
                'environment' => $this->enviroment
            ]);
        }

        // Para configuração de webhook, adicionar certificados TLS mútuo
        if ($isWebhookConfig) {
            $clientCertPath = $this->enviroment === 'local' 
                ? storage_path('app/certificates/cliente.pem')
                : storage_path('app/certificates/cliente_prd.pem');
                
            $clientKeyPath = $this->enviroment === 'local' 
                ? storage_path('app/certificates/cliente.key')
                : storage_path('app/certificates/cliente_prd.key');

            if (file_exists($clientCertPath)) {
                $curlOptions[CURLOPT_SSLCERT] = $clientCertPath;
                
                Log::info('Usando certificado cliente para webhook', [
                    'cert_path' => $clientCertPath,
                    'environment' => $this->enviroment
                ]);
            }

            if (file_exists($clientKeyPath)) {
                $curlOptions[CURLOPT_SSLKEY] = $clientKeyPath;
                
                Log::info('Usando chave cliente para webhook', [
                    'key_path' => $clientKeyPath,
                    'environment' => $this->enviroment
                ]);
            }

            // Para webhook em produção, usar CA info se disponível
            if ($this->enviroment !== 'local' && file_exists($this->certificadoPath)) {
                $curlOptions[CURLOPT_CAINFO] = $this->certificadoPath;
            }
        }

        if ($body) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        // Log adicional para debug de SSL
        if ($error && strpos($error, 'SSL') !== false) {
            Log::warning('Erro SSL detectado na requisição', [
                'url' => $url,
                'error' => $error,
                'environment' => $this->enviroment,
                'is_webhook_config' => $isWebhookConfig,
                'cert_path' => $this->certificadoPath
            ]);
        }

        return [
            'success' => !$error && ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'error' => $error,
            'data' => $response ? json_decode($response, true) : null,
            'url' => $url,
            'body' => $body
        ];
    }

    public function atualizarCobranca(Request $request): JsonResponse
    {
        try {
            // Validar autenticação TLS mútuo (apenas EFI deve acessar este endpoint)
            if (!$this->validarTlsMutuo($request)) {
                Log::warning('Tentativa de acesso ao webhook sem TLS mútuo válido', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'headers' => $request->headers->all(),
                    'ssl_client_cert' => $_SERVER['SSL_CLIENT_CERT'] ?? 'não presente'
                ]);
                
                return response()->json([
                    'codRetorno' => 403,
                    'message' => 'Acesso negado. Este endpoint requer autenticação TLS mútuo válida.',
                    'observacao' => 'Apenas a EFI Pay pode acessar este webhook com certificados válidos.'
                ], 403);
            }

            $recs = $request->recs;
            $resultados = [];

            // Processar cada REC da requisição
            if (!is_array($recs)) {
                Log::warning('Campo "recs" ausente ou inválido na requisição', ['recs' => $recs]);
                $recs = [];
                return response()->json([
                    'codRetorno' => 200,
                    'message' => 'Nenhum REC fornecido para atualização',
                ]);     
            }

            foreach ($recs as $rec) {
                if (!isset($rec['idRec']) || !isset($rec['status'])) {
                    Log::warning('REC inválido recebido - faltando idRec ou status', ['rec' => $rec]);
                    continue;
                }

                $idRec = $rec['idRec'];
                $status = $rec['status'];

                // Buscar o pagamento PIX no banco de dados pelo recId
                $pagamentoPix = PagamentoPix::where('recId', $idRec)->first();

                if ($pagamentoPix) {
                    // Atualizar status no banco de dados
                    $statusAnterior = $pagamentoPix->status;
                    $pagamentoPix->status = $status;
                    $pagamentoPix->dataPagamento = now(); // Atualiza a data de pagamento
                    $pagamentoPix->save();

                    Log::info('Status da cobrança PIX atualizado', [
                        'idRec' => $idRec,
                        'status_anterior' => $statusAnterior,
                        'status_novo' => $status,
                        'pagamento_id' => $pagamentoPix->id
                    ]);

                    $resultados[] = [
                        'idRec' => $idRec,
                        'status' => $status,
                        'status_anterior' => $statusAnterior,
                        'atualizado' => true
                    ];

                $usuario = Usuarios::find($pagamentoPix->idUsuario);
                    if ($usuario) {
                        // Enviar email de notificação
                      $usuario->status = 1;
                      $usuario->dataUltimoPagamento = now();
                      $usuario->idUltimaCobranca = $idRec;
                      $usuario->save();
                    }
                } else {
                    Log::warning('Pagamento PIX não encontrado para o idRec', ['idRec' => $idRec]);
                    
                    $resultados[] = [
                        'idRec' => $idRec,
                        'status' => $status,
                        'atualizado' => false,
                        'motivo' => 'Pagamento não encontrado no sistema'
                    ];
                }
            }

            return response()->json([
                'codRetorno' => 200,
                'message' => 'Atualização de cobranças processada',
                'total_processados' => count($resultados),
                'resultados' => $resultados
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar cobrança PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao processar atualização de cobranças',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configura webhook para notificações de cobrança PIX
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function configurarWebhook(Request $request): JsonResponse
    {
        try {
            // Verificar se foi fornecida uma URL customizada ou usar a padrão configurada
            $webhookUrl = $request->input('webhookUrl');
            
            if (!$webhookUrl) {
                // Usar URL configurada no .env para webhook com TLS mútuo
                $webhookUrl = env('WEBHOOK_PIX_URL');
                
                // Se não estiver configurada, usar URL base como fallback
                if (!$webhookUrl) {
                    $webhookUrl = env('APP_URL') . '/api/pix/atualizar';
                }
                
                if (!$webhookUrl) {
                    return response()->json([
                        'codRetorno' => 400,
                        'message' => 'URL do webhook não configurada. Configure WEBHOOK_PIX_URL no .env ou forneça webhookUrl na requisição.',
                        'observacao' => 'A URL deve estar configurada com autenticação TLS mútuo conforme documentação da EFI'
                    ], 400);
                }
            }

            // Validar se a URL é válida
            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'codRetorno' => 400,
                    'message' => 'URL do webhook inválida',
                    'webhookUrl' => $webhookUrl
                ], 400);
            }

            Log::info('Tentando configurar webhook PIX', [
                'webhook_url' => $webhookUrl,
                'origem' => $request->input('webhookUrl') ? 'requisicao' : 'env'
            ]);

            // Configurar webhook na API EFI
            $webhookResponse = $this->configurarWebhookEfi($webhookUrl);
            
            if ($webhookResponse['success']) {
                Log::info('Webhook PIX configurado com sucesso', [
                    'webhook_url' => $webhookUrl,
                    'response' => $webhookResponse['data']
                ]);

                return response()->json([
                    'codRetorno' => 200,
                    'message' => 'Webhook configurado com sucesso',
                    'data' => [
                        'response' => $webhookResponse['data'],
                        'webhookUrl' => $webhookUrl,
                        'configurado_em' => now()->toDateTimeString(),
                        'observacao' => 'Webhook configurado com autenticação TLS mútuo'
                    ]
                ]);
            } else {
                $errorMessage = $this->interpretarErroWebhook($webhookResponse);
                
                Log::error('Erro ao configurar webhook PIX', [
                    'webhook_url' => $webhookUrl,
                    'error_response' => $webhookResponse,
                    'interpreted_error' => $errorMessage
                ]);

                return response()->json([
                    'codRetorno' => 500,
                    'message' => 'Erro ao configurar webhook',
                    'error' => $errorMessage,
                    'detalhes' => $webhookResponse['data'] ?? null,
                    'sugestoes' => [
                        'Verifique se a URL possui certificado SSL válido',
                        'Confirme se a autenticação TLS mútuo está configurada',
                        'Teste se a URL está acessível externamente',
                        'Consulte a documentação da EFI sobre configuração de webhooks'
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Erro geral ao configurar webhook PIX', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro interno ao configurar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Interpreta erros comuns de configuração de webhook
     */
    private function interpretarErroWebhook(array $webhookResponse): string
    {
        $httpCode = $webhookResponse['http_code'] ?? 0;
        $responseData = $webhookResponse['data'] ?? [];
        $error = $webhookResponse['error'] ?? '';
        
        // Verificar erros SSL específicos
        if (strpos($error, 'SSL certificate problem') !== false) {
            if (strpos($error, 'self-signed certificate') !== false) {
                $ambiente = config('app.env');
                if ($ambiente === 'local' || $ambiente === 'development') {
                    return 'SSL certificate problem: self-signed certificate in certificate chain. SOLUÇÃO PARA DESENVOLVIMENTO: Configure SSL_VERIFY_DISABLED=true no arquivo .env para desabilitar verificação SSL em ambiente local.';
                } else {
                    return 'SSL certificate problem: self-signed certificate in certificate chain. SOLUÇÃO PARA PRODUÇÃO: Use certificados SSL válidos emitidos por autoridade certificadora confiável.';
                }
            }
            
            if (strpos($error, 'unable to get local issuer certificate') !== false) {
                return 'SSL certificate problem: unable to get local issuer certificate. SOLUÇÃO: Atualize os certificados CA do sistema ou configure SSL_VERIFY_DISABLED=true para desenvolvimento.';
            }
            
            return 'Problema com certificado SSL. Verifique se os certificados estão corretos e acessíveis. Para desenvolvimento: configure SSL_VERIFY_DISABLED=true no .env';
        }
        
        if (strpos($error, 'SSL connect error') !== false || strpos($error, 'TLS handshake') !== false) {
            return 'Erro de conexão SSL/TLS. Verifique: 1) URL suporta HTTPS, 2) Certificados cliente em storage/app/certificates/, 3) TLS mútuo configurado no servidor de destino.';
        }
        
        // Verificar códigos de erro conhecidos
        switch ($httpCode) {
            case 0:
                if (!empty($error)) {
                    // Analisar erro mais detalhadamente
                    if (strpos($error, 'Could not resolve host') !== false) {
                        return "Erro de DNS: {$error}. Verifique se a URL do webhook está correta e acessível.";
                    }
                    if (strpos($error, 'Connection timed out') !== false) {
                        return "Timeout de conexão: {$error}. Verifique conectividade de rede e firewall.";
                    }
                    return "Erro de conexão: {$error}";
                }
                return 'Falha na conexão com a API EFI. Verifique a conectividade de rede e URL do webhook.';
                
            case 400:
                if (isset($responseData['detail']) && strpos($responseData['detail'], 'TLS') !== false) {
                    return 'Autenticação TLS mútuo não está configurada na URL informada. Configure um certificado SSL com autenticação mútua no servidor de destino.';
                }
                return 'Dados da requisição inválidos ou URL malformada. Verifique se a URL do webhook está correta.';
                
            case 401:
                return 'Credenciais de autenticação inválidas para a API EFI. Verifique CLIENT_ID e CLIENT_SECRET no .env.';
                
            case 403:
                return 'Acesso negado. Verifique as permissões da conta EFI e se os certificados estão corretos.';
                
            case 404:
                return 'Endpoint de configuração de webhook não encontrado na API EFI.';
                
            case 422:
                return 'URL do webhook não atende aos requisitos da EFI. Requisitos: HTTPS obrigatório, TLS mútuo configurado, URL acessível externamente.';
                
            case 500:
                return 'Erro interno da API EFI. Tente novamente em alguns minutos.';
                
            default:
                if (isset($responseData['detail'])) {
                    return $responseData['detail'];
                }
                if (isset($responseData['message'])) {
                    return $responseData['message'];
                }
                return $error ?: 'Erro desconhecido ao configurar webhook. Verifique logs do sistema para mais detalhes.';
        }
    }

    /**
     * Configura webhook na API EFI - PUT /v2/webhookcobr
     */
    private function configurarWebhookEfi(string $webhookUrl): array
    {
        $url = $this->buildApiUrl("/v2/webhookcobr");
        
        $body = json_encode([
            "webhookUrl" => $webhookUrl
        ]);

        // Usar certificados TLS mútuo para configuração de webhook
        return $this->executeApiRequest($url, 'PUT', $body, true);
    }

    /**
     * Verifica status das configurações SSL e certificados
     * GET /api/pix/ssl-status
     */
    public function sslStatus(): JsonResponse
    {
        try {
            $sslVerifyDisabled = config('app.ssl_verify_disabled', false) || env('SSL_VERIFY_DISABLED', false);
            $environment = config('app.env');
            
            // Verificar certificados disponíveis
            $certificates = [
                'cliente_homologacao' => file_exists(storage_path('app/certificates/cliente.pem')) ? 'presente' : 'ausente',
                'cliente_key_homologacao' => file_exists(storage_path('app/certificates/cliente.key')) ? 'presente' : 'ausente',
                'cliente_producao' => file_exists(storage_path('app/certificates/cliente_prd.pem')) ? 'presente' : 'ausente',
                'cliente_key_producao' => file_exists(storage_path('app/certificates/cliente_prd.key')) ? 'presente' : 'ausente',
                'efi_homologacao' => file_exists(storage_path('app/certificates/hml.pem')) ? 'presente' : 'ausente',
                'efi_producao' => file_exists(storage_path('app/certificates/prd.pem')) ? 'presente' : 'ausente'
            ];
            
            $webhookUrl = env('WEBHOOK_PIX_URL');
            
            return response()->json([
                'codRetorno' => 200,
                'dados' => [
                    'environment' => $environment,
                    'ssl_verify_disabled' => $sslVerifyDisabled,
                    'webhook_url' => $webhookUrl,
                    'certificates' => $certificates,
                    'configuracao_ssl' => [
                        'verificacao_ssl' => $sslVerifyDisabled ? 'desabilitada' : 'ativa',
                        'tls_mutuo' => 'configurado_automaticamente',
                        'certificados_cliente' => $environment === 'local' ? 'homologacao' : 'producao'
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao verificar status SSL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao verificar status SSL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint de teste para validação de TLS mútuo
     * GET /api/pix/test-tls
     */
    public function testTlsMutual(Request $request): JsonResponse
    {
        try {
            // Informações sobre o certificado cliente
            $sslInfo = [
                'ssl_client_cert' => !empty($_SERVER['SSL_CLIENT_CERT']) ? 'presente' : 'ausente',
                'ssl_client_verify' => $_SERVER['SSL_CLIENT_VERIFY'] ?? 'não verificado',
                'ssl_client_subject' => $_SERVER['SSL_CLIENT_S_DN'] ?? 'não disponível',
                'ssl_client_issuer' => $_SERVER['SSL_CLIENT_I_DN'] ?? 'não disponível',
                'ssl_protocol' => $_SERVER['SSL_PROTOCOL'] ?? 'não disponível',
                'ssl_cipher' => $_SERVER['SSL_CIPHER'] ?? 'não disponível'
            ];
            
            // Informações da requisição
            $requestInfo = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ];
            
            // Verificar se passaria na validação TLS
            $tlsValid = $this->validarTlsMutuo($request);
            
            return response()->json([
                'codRetorno' => 200,
                'message' => 'Teste de TLS mútuo executado',
                'dados' => [
                    'tls_mutuo_valido' => $tlsValid,
                    'ambiente' => config('app.env'),
                    'ssl_verify_disabled' => env('SSL_VERIFY_DISABLED', false),
                    'ssl_info' => $sslInfo,
                    'request_info' => $requestInfo,
                    'observacao' => $tlsValid 
                        ? 'Requisição passaria na validação TLS mútuo' 
                        : 'Requisição seria rejeitada por TLS mútuo inválido'
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao testar TLS mútuo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida se a requisição possui autenticação TLS mútuo válida
     * Verifica certificados cliente para garantir que apenas a EFI acesse o webhook
     */
    private function validarTlsMutuo(Request $request): bool
    {
        // Em ambiente de desenvolvimento, permitir testes sem TLS mútuo
        if ($this->enviroment === 'local' && env('SSL_VERIFY_DISABLED', false)) {
            Log::info('TLS mútuo desabilitado em ambiente de desenvolvimento', [
                'environment' => $this->enviroment,
                'ssl_verify_disabled' => env('SSL_VERIFY_DISABLED', false)
            ]);
            return true;
        }
        
        // Verificar se o certificado cliente está presente
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? null;
        $clientCertVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? null;
        
        if (!$clientCert) {
            Log::warning('Certificado cliente não apresentado na requisição TLS', [
                'ssl_client_verify' => $clientCertVerify,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        // Verificar se o certificado foi verificado com sucesso
        if ($clientCertVerify !== 'SUCCESS') {
            Log::warning('Certificado cliente falhou na verificação', [
                'ssl_client_verify' => $clientCertVerify,
                'ssl_client_cert_subject' => $_SERVER['SSL_CLIENT_S_DN'] ?? 'unknown'
            ]);
            return false;
        }
        
        // Validar se é um certificado da EFI
        return $this->validarCertificadoEfi($clientCert);
    }
    
    /**
     * Valida se o certificado apresentado é válido da EFI
     */
    private function validarCertificadoEfi(string $clientCert): bool
    {
        try {
            // Parse do certificado
            $certInfo = openssl_x509_parse($clientCert);
            
            if (!$certInfo) {
                Log::error('Erro ao fazer parse do certificado cliente');
                return false;
            }
            
            // Verificar se é um certificado da EFI
            $subject = $certInfo['subject'] ?? [];
            $issuer = $certInfo['issuer'] ?? [];
            
            // Verificar domínios/organizações conhecidas da EFI
            $efiDomains = [
                'efipay.com.br',
                'gerencianet.com.br',
                'efi.com.br'
            ];
            
            $efiOrganizations = [
                'EFI Pay',
                'Gerencianet',
                'EFI S.A.'
            ];
            
            // Verificar subject
            $commonName = $subject['CN'] ?? '';
            $organization = $subject['O'] ?? '';
            
            // Verificar se o CN contém domínio da EFI
            foreach ($efiDomains as $domain) {
                if (strpos($commonName, $domain) !== false) {
                    Log::info('Certificado EFI válido - domínio reconhecido', [
                        'common_name' => $commonName,
                        'domain_matched' => $domain
                    ]);
                    return true;
                }
            }
            
            // Verificar se a organização é da EFI
            foreach ($efiOrganizations as $org) {
                if (strpos($organization, $org) !== false) {
                    Log::info('Certificado EFI válido - organização reconhecida', [
                        'organization' => $organization,
                        'org_matched' => $org
                    ]);
                    return true;
                }
            }
            
            // Log para debug em desenvolvimento
            Log::warning('Certificado não reconhecido como EFI', [
                'subject' => $subject,
                'issuer' => $issuer,
                'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t'] ?? 0),
                'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t'] ?? 0)
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Erro ao validar certificado EFI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

}