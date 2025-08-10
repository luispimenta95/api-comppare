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

    public function atualizarCobrança(Request $request): void
    {
        Log::info('Atualizando cobrança', [
            'request_data' => $request->all()
        ]);
    }
}
