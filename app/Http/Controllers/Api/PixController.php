<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PixController extends Controller
{
    private ApiEfi $apiEfi;
    private string $enviroment;
    private string $certificadoPath;


    public function __construct()
    {
        $this->apiEfi = new ApiEfi();
        $this->enviroment = config('app.env'); // Assuming you have an env variable for environment
        $this->certificadoPath = $this->enviroment == "local"
            ? storage_path('app/certificates/hml.pem')
            : storage_path('app/certificates/prd.pem');
    }

    /**
     * Cria uma cobrança PIX recorrente
     * 
     * Exemplo de uso:
     * POST /api/pix/recorrente
     * {
     *   "contrato": "63100862",
     *   "devedor": {
     *     "cpf": "45164632481",
     *     "nome": "Fulano de Tal"
     *   },
     *   "objeto": "Serviço de Streamming de Música",
     *   "dataFinal": "2025-04-01",
     *   "dataInicial": "2024-04-01",
     *   "periodicidade": "MENSAL",
     *   "valor": "35.00",
     *   "politicaRetentativa": "NAO_PERMITE",
     *   "loc": 108,
     *   "txid": "33beb661beda44a8928fef47dbeb2dc5"
     * }
     */
    public function createRecurrentCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contrato' => 'required|string',
            'devedor.cpf' => 'required|string|size:11',
            'devedor.nome' => 'required|string|max:255',
            'objeto' => 'nullable|string|max:255',
            'dataFinal' => 'required|date_format:Y-m-d',
            'dataInicial' => 'required|date_format:Y-m-d',
            'periodicidade' => 'nullable|string|in:MENSAL,SEMANAL,ANUAL',
            'valor' => 'required|numeric|min:0.01',
            'politicaRetentativa' => 'nullable|string|in:PERMITE,NAO_PERMITE',
            'loc' => 'nullable|integer',
            'txid' => 'nullable|string'
        ]);

        $result = $this->apiEfi->createPixRecurrentCharge($validated);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar cobrança PIX recorrente',
                'error' => $response
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cobrança PIX recorrente criada com sucesso',
            'data' => $response
        ]);
    }

    /**
     * Cria uma cobrança PIX com QR Code dinâmico
     * 
     * Exemplo de uso:
     * POST /api/pix/dinamico
     * {
     *   "devedor": {
     *     "cpf": "45164632481",
     *     "nome": "Fulano de Tal"
     *   },
     *   "valor": 35.00,
     *   "descricao": "Pagamento de assinatura",
     *   "expiracao": 3600,
     *   "chave_pix": "sua_chave_pix@email.com"
     * }
     */
    public function createDynamicCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'devedor.cpf' => 'required|string|size:11',
            'devedor.nome' => 'required|string|max:255',
            'valor' => 'required|numeric|min:0.01',
            'descricao' => 'nullable|string|max:255',
            'expiracao' => 'nullable|integer|min:1',
            'chave_pix' => 'nullable|string',
            'infoAdicionais' => 'nullable|array'
        ]);

        $result = $this->apiEfi->createPixDynamicCharge($validated);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar cobrança PIX dinâmica',
                'error' => $response
            ], 400);
        }

        // Se a cobrança foi criada com sucesso, gerar o QR Code
        if (isset($response['txid'])) {
            $qrCodeResult = $this->apiEfi->generatePixQRCode($response['txid']);
            $qrCodeResponse = json_decode($qrCodeResult, true);
            
            if (!isset($qrCodeResponse['code'])) {
                $response['qr_code'] = $qrCodeResponse;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cobrança PIX dinâmica criada com sucesso',
            'data' => $response
        ]);
    }

    /**
     * Gera QR Code para uma cobrança PIX existente
     * 
     * GET /api/pix/qrcode/{txid}
     */
    public function generateQRCode(string $txid): JsonResponse
    {
        $result = $this->apiEfi->generatePixQRCode($txid);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar QR Code',
                'error' => $response
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR Code gerado com sucesso',
            'data' => $response
        ]);
    }

     public function enviarPix(array $dados): mixed
    {
        try {
            // Obter token de autenticação
            $tokenResponse = $this->apiEfi->getToken();
            
            // Verificar se o token foi obtido com sucesso
            if (is_string($tokenResponse) && str_starts_with($tokenResponse, '{"code"')) {
                Log::error('ApiEfi - Erro ao obter token para enviar PIX', [
                    'token_response' => $tokenResponse
                ]);
                return $tokenResponse; // Retorna o erro do token
            }
            
            if (empty($tokenResponse)) {
                return json_encode([
                    "code" => 401,
                    "Erro" => "Token inválido",
                    "description" => "Não foi possível obter token de autenticação"
                ]);
            }
            
            Log::info('ApiEfi - Token obtido para enviar PIX', [
                'token_length' => strlen($tokenResponse)
            ]);
            
            // Determinar URL baseada no ambiente
            $baseUrl = $this->enviroment == "local" ? 
                'https://pix-h.api.efipay.com.br/v2/cob/' : 
                'https://pix.api.efipay.com.br/v2/cob/';
            
            // Montar body da requisição
            $body = [
                "calendario" => [
                    "expiracao" => $dados['expiracao'] ?? 3600
                ],
                "devedor" => [
                    "cpf" => $dados['devedor']['cpf'],
                    "nome" => $dados['devedor']['nome']
                ],
                "valor" => [
                    "original" => number_format($dados['valor'], 2, '.', '')
                ],
                "chave" => $dados['chave'] ?? env('PIX_KEY', 'chave.pix@email.com.br')
            ];
            
            // Adicionar descrição se fornecida
            if (isset($dados['descricao'])) {
                $body['solicitacaoPagador'] = $dados['descricao'];
            }
            
            // Adicionar informações adicionais se fornecidas
            if (isset($dados['infoAdicionais'])) {
                $body['infoAdicionais'] = $dados['infoAdicionais'];
            }
            
            Log::info('ApiEfi - Criando cobrança PIX', [
                'url' => $baseUrl,
                'body_keys' => array_keys($body),
                'valor' => $body['valor']['original']
            ]);
            
            // Primeira requisição: criar cobrança PIX
            $curl = curl_init();
            
            $curlOptions = [
                CURLOPT_URL => $baseUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$tokenResponse}",
                    "Content-Type: application/json"
                ],
            ];
            
            // Adicionar certificado se existir
            if (file_exists($this->certificadoPath) && is_readable($this->certificadoPath)) {
                $curlOptions[CURLOPT_SSLCERT] = $this->certificadoPath;
                $curlOptions[CURLOPT_SSLCERTPASSWD] = env('EFI_CERTIFICADO_PASSWORD', '');
                $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
                $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;
                
                Log::info('ApiEfi - Usando certificado SSL para PIX', [
                    'certificado' => $this->certificadoPath
                ]);
            } else {
                Log::warning('ApiEfi - PIX sem certificado (não recomendado)', [
                    'certificado_esperado' => $this->certificadoPath
                ]);
                $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
                $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            }
            
            curl_setopt_array($curl, $curlOptions);
            
            $responsePix = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);
            
            // Verificar erros de cURL na primeira requisição
            if ($error) {
                Log::error('ApiEfi - Erro cURL ao criar PIX', [
                    'error' => $error,
                    'http_code' => $httpCode
                ]);
                
                return json_encode([
                    "code" => 500,
                    "Erro" => "Erro de conectividade",
                    "description" => $error
                ]);
            }
            
            // Verificar código HTTP da primeira requisição
            if ($httpCode !== 200 && $httpCode !== 201) {
                Log::error('ApiEfi - Código HTTP inválido ao criar PIX', [
                    'http_code' => $httpCode,
                    'response' => $responsePix
                ]);
                
                return json_encode([
                    "code" => $httpCode,
                    "Erro" => "Erro HTTP ao criar PIX",
                    "description" => "Código HTTP: $httpCode",
                    "response" => $responsePix
                ]);
            }
            
            $responsePixData = json_decode($responsePix, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('ApiEfi - Erro ao decodificar resposta PIX', [
                    'response' => $responsePix,
                    'json_error' => json_last_error_msg()
                ]);
                
                return json_encode([
                    "code" => 500,
                    "Erro" => "Erro de formato",
                    "description" => "Resposta PIX não é um JSON válido"
                ]);
            }
            
            Log::info('ApiEfi - PIX criado com sucesso', [
                'txid' => $responsePixData['txid'] ?? 'N/A',
                'loc_id' => $responsePixData['loc']['id'] ?? 'N/A'
            ]);
            
            // Verificar se temos o ID da localização para gerar QR Code
            if (isset($responsePixData['loc']['id'])) {
                $idlocationPix = $responsePixData['loc']['id'];
                
                // Segunda requisição: obter QR Code
                $qrCodeUrl = $this->enviroment == "local" ? 
                    "https://pix-h.api.efipay.com.br/v2/loc/{$idlocationPix}/qrcode" : 
                    "https://pix.api.efipay.com.br/v2/loc/{$idlocationPix}/qrcode";
                
                Log::info('ApiEfi - Obtendo QR Code PIX', [
                    'location_id' => $idlocationPix,
                    'qr_url' => $qrCodeUrl
                ]);
                
                $curlQr = curl_init();
                
                $curlQrOptions = [
                    CURLOPT_URL => $qrCodeUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        "Authorization: Bearer {$tokenResponse}",
                    ],
                ];
                
                // Adicionar certificado se existir
                if (file_exists($this->certificadoPath) && is_readable($this->certificadoPath)) {
                    $curlQrOptions[CURLOPT_SSLCERT] = $this->certificadoPath;
                    $curlQrOptions[CURLOPT_SSLCERTPASSWD] = env('EFI_CERTIFICADO_PASSWORD', '');
                    $curlQrOptions[CURLOPT_SSL_VERIFYPEER] = true;
                    $curlQrOptions[CURLOPT_SSL_VERIFYHOST] = 2;
                } else {
                    $curlQrOptions[CURLOPT_SSL_VERIFYPEER] = false;
                    $curlQrOptions[CURLOPT_SSL_VERIFYHOST] = false;
                }
                
                curl_setopt_array($curlQr, $curlQrOptions);
                
                $responseQr = curl_exec($curlQr);
                $httpCodeQr = curl_getinfo($curlQr, CURLINFO_HTTP_CODE);
                $errorQr = curl_error($curlQr);
                
                curl_close($curlQr);
                
                // Verificar se conseguiu obter QR Code
                if (!$errorQr && ($httpCodeQr === 200 || $httpCodeQr === 201)) {
                    $qrCodeData = json_decode($responseQr, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Combinar dados da cobrança com QR Code
                        $responsePixData['qrcode'] = $qrCodeData;
                        
                        Log::info('ApiEfi - QR Code obtido com sucesso', [
                            'qr_code_length' => strlen($qrCodeData['qrcode'] ?? ''),
                            'copy_paste_length' => strlen($qrCodeData['pixCopiaECola'] ?? '')
                        ]);
                    } else {
                        Log::warning('ApiEfi - Erro ao decodificar QR Code', [
                            'response_qr' => $responseQr
                        ]);
                    }
                } else {
                    Log::warning('ApiEfi - Não foi possível obter QR Code', [
                        'error_qr' => $errorQr,
                        'http_code_qr' => $httpCodeQr,
                        'response_qr' => $responseQr
                    ]);
                }
            }
            
            // Retornar resposta completa
            return json_encode($responsePixData);
            
        } catch (\Exception $e) {
            Log::error('ApiEfi - Erro geral ao enviar PIX', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return json_encode([
                "code" => 500,
                "Erro" => "Erro interno",
                "description" => $e->getMessage()
            ]);
        }
    }
}
