<?php

namespace App\Http\Util\Payments;

use App\Http\Util\Helper;
use Efi\Exception\EfiException;
use Efi\EfiPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ApiEfi
{

    private array $options = [];
    private array $params = [];
    private string $enviroment;
    private EfiPay $efiPay;
    private string $url;
    private string $certificadoPath;
    
    public function __construct()
{
    $this->enviroment = env('APP_ENV');
    $this->url = $this->enviroment == "local" ?
        env("APP_URL") . '/api/notification?sandbox=true' :
        env("APP_URL") . "/api/notification";

    $this->options = [
        "clientId" => $this->enviroment == "local" ? env('ID_EFI_HML') : env('ID_EFI_PRD'),
        "clientSecret" => $this->enviroment == "local" ? env('SECRET_EFI_HML') : env('SECRET_EFI_PRD'),
        "sandbox" => $this->enviroment == "local" ? true : false,
        "debug" => false, // Ativar debug para ver requisições
        "timeout" => 30, // Aumentar timeout
        "responseHeaders" => true
    ];
    
    $this->certificadoPath = $this->enviroment == "local"
        ? storage_path('app/certificates/hml.pem')
        : storage_path('app/certificates/prd.pem');

    // Inicializar o EfiPay
    try {
        $this->efiPay = new EfiPay($this->options);
        Log::info('ApiEfi - EfiPay inicializado com sucesso');
    } catch (\Exception $e) {
        throw new \Exception('Falha na inicialização do EfiPay: ' . $e->getMessage());
    }
}


    public function createPlan(string $name, int $frequencia): mixed
    {
        try {
            $body = [
                "name" => $name,
                "interval" => $frequencia,
                "repeats" => null
            ];

            $response = $this->efiPay->createPlan($this->params, $body);
            return json_encode($response);
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }
    public function createSubscription(array $dados): mixed
    {
        $params = [
            "id" => $dados["idPlano"],
        ];
        $dados['produto']['value'] = (int) round($dados["produto"]['value']);
        $body = [
            "items" =>  [$dados['produto']],
            "metadata" =>  ["notification_url" =>  $this->url],
            "payment" => [
                "credit_card" => [
                    "trial_days" =>  Helper::TEMPO_GRATUIDADE,
                    "payment_token" =>  $dados['cardToken'],
                    "customer" =>  $dados['usuario']
                ]
            ]
        ];
        Log::info("Value:" . $body['items'][0]['value']); //
        try {
            return json_encode($this->efiPay->createOneStepSubscription($params, $body));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }
    public function getSubscriptionDetail(string $token): mixed
    {
        try {
            $params = [
                "token" => $token
            ];
            //Erro ao recuperar dados
            return json_encode($this->efiPay->getNotification($params));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }

    public function cancelSubscription($id): mixed
    {
        try {
            $params = [
                "id" => (int)$id
            ];
            //Erro ao recuperar dados
            return json_encode($this->efiPay->cancelSubscription($params));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }

    public function createPixCharge(array $dados): mixed
    {
        try {
            $body = [
                "items" =>  [$dados['produto']],
                "metadata" =>  ["notification_url" =>  $this->url],
                "payment" => [
                    "pix" => [
                        "customer" =>  $dados['usuario']
                    ]
                ]
            ];
            return json_encode($this->efiPay->pixCreateCharge($this->params, $body));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }

    /**
     * Cria uma cobrança PIX recorrente baseada no modelo de vínculo
     * 
     * @param array $dados - Dados da cobrança recorrente
     * @return mixed
     */
    public function createPixRecurrentCharge(array $dados): mixed
    {
        try {
              $token = $this->getToken();
              $this->params['headers']['Authorization'] = "Bearer {$token}";
              $this->params['certificate'] = $this->certificadoPath;
            $body = [
                "vinculo" => [
                    "contrato" => $dados['contrato'],
                    "devedor" => [
                        "cpf" => $dados['devedor']['cpf'],
                        "nome" => $dados['devedor']['nome']
                    ],
                    "objeto" => $dados['objeto'] ?? "Serviço de Streamming de Música."
                ],
                "calendario" => [
                    "dataFinal" => $dados['dataFinal'],
                    "dataInicial" => $dados['dataInicial'],
                    "periodicidade" => $dados['periodicidade'] ?? "MENSAL"
                ],
                "valor" => [
                    "valorRec" => $dados['valor']
                ],
                "politicaRetentativa" => $dados['politicaRetentativa'] ?? "NAO_PERMITE"
            ];

            // Adiciona loc se fornecido
            if (isset($dados['loc'])) {
                $body['loc'] = $dados['loc'];
            }

            // Adiciona ativação se fornecido
            if (isset($dados['txid'])) {
                $body['ativacao'] = [
                    "dadosJornada" => [
                        "txid" => $dados['txid']
                    ]
                ];
            }

            return json_encode($this->efiPay->pixCreateCharge($this->params, $body));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }

    /**
     * Cria uma cobrança PIX com QR Code dinâmico
     * 
     * @param array $dados - Dados da cobrança PIX
     * @return mixed
     */
    public function createPixDynamicCharge(array $dados): mixed
    {
        try {
            $body = [
                "calendario" => [
                    "expiracao" => $dados['expiracao'] ?? 3600 // 1 hora por padrão
                ],
                "devedor" => [
                    "cpf" => $dados['devedor']['cpf'],
                    "nome" => $dados['devedor']['nome']
                ],
                "valor" => [
                    "original" => number_format($dados['valor'], 2, '.', '')
                ],
                "chave" => $dados['chave_pix'] ?? env('PIX_KEY'), // Chave PIX configurada
                "solicitacaoPagador" => $dados['descricao'] ?? "Pagamento de serviços"
            ];

            // Adiciona informações adicionais se fornecidas
            if (isset($dados['infoAdicionais'])) {
                $body['infoAdicionais'] = $dados['infoAdicionais'];
            }

            return json_encode($this->efiPay->pixCreateCharge($this->params, $body));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }

    /**
     * Gera o QR Code para uma cobrança PIX criada
     * 
     * @param string $txid - ID da transação PIX
     * @return mixed
     */
    public function generatePixQRCode(string $txid): mixed
    {
        try {
            $params = [
                "txid" => $txid
            ];

            return json_encode($this->efiPay->pixGenerateQRCode($params));
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }
    /*
   * Obtém token de autenticação da API EFI Pay usando cURL direto
 * Baseado no exemplo oficial da EFI Pay
 * 
 * @return mixed
 */
    public function getToken(): mixed
    {
        try {
            Log::info('ApiEfi - Solicitando token de autenticação via cURL');

            // Configurações baseadas no ambiente
            $clientId = $this->enviroment == "local" ? env('ID_EFI_HML') : env('ID_EFI_PRD');
            $clientSecret = $this->enviroment == "local" ? env('SECRET_EFI_HML') : env('SECRET_EFI_PRD');
            $baseUrl = $this->enviroment == "local" ?
                "https://pix-h.api.efipay.com.br" :
                "https://pix.api.efipay.com.br";

            Log::info('ApiEfi - Verificando certificado', [
                'certificado_path' => $this->certificadoPath,
                'arquivo_existe' => file_exists($this->certificadoPath),
                'ambiente' => $this->enviroment
            ]);

            // Validar credenciais
            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('Credenciais EFI Pay não configuradas');
            }

            // Codificação da autorização
            $autorizacao = base64_encode($clientId . ":" . $clientSecret);

            // Inicializar cURL
            $curl = curl_init();

            $curlOptions = [
                CURLOPT_URL => $baseUrl . "/oauth/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_SSLCERTPASSWD => '',
                CURLOPT_POSTFIELDS => '{"grant_type": "client_credentials"}',
                CURLOPT_HTTPHEADER => [
                    "Authorization: Basic $autorizacao",
                    "Content-Type: application/json"
                ],
            ];

            // Verificar se o certificado existe e adicionar às opções
            if (file_exists($this->certificadoPath)) {
                // Verificar se é um arquivo válido e legível
                if (is_readable($this->certificadoPath)) {
                    $curlOptions[CURLOPT_SSLCERT] = $this->certificadoPath;
                    $curlOptions[CURLOPT_SSLCERTPASSWD] = env('EFI_CERTIFICADO_PASSWORD', '');
                    $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
                    $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;

                    Log::info('ApiEfi - Usando certificado SSL', ['certificado' => $this->certificadoPath]);
                } else {
                    Log::warning('ApiEfi - Certificado não é legível, continuando sem SSL client cert', [
                        'certificado' => $this->certificadoPath
                    ]);
                    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
                    $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
                }
            } else {
                Log::warning('ApiEfi - Certificado não encontrado, continuando sem SSL client cert', [
                    'certificado_esperado' => $this->certificadoPath
                ]);
                // Para testes sem certificado (não recomendado em produção)
                $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
                $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            }

            curl_setopt_array($curl, $curlOptions);

            Log::info('ApiEfi - Executando requisição para obter token', [
                'url' => $baseUrl . "/oauth/token",
                'environment' => $this->enviroment,
                'usando_certificado' => file_exists($this->certificadoPath) && is_readable($this->certificadoPath)
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            curl_close($curl);

            // Verificar erros de cURL
            if ($error) {
                Log::error('ApiEfi - Erro cURL ao obter token', [
                    'error' => $error,
                    'http_code' => $httpCode
                ]);

                return json_encode([
                    "code" => 500,
                    "Erro" => "Erro de conectividade",
                    "description" => $error
                ]);
            }

            // Verificar código HTTP
            if ($httpCode !== 200) {
                Log::error('ApiEfi - Código HTTP inválido ao obter token', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);

                return json_encode([
                    'MSG' => "Erro ao obter token",
                    "code" => $httpCode,
                    "Erro" => "Erro HTTP",
                    "description" => "Código HTTP: $httpCode",
                    "response" => $response
                ]);
            }

            // Decodificar resposta
            $responseData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('ApiEfi - Erro ao decodificar resposta JSON', [
                    'response' => $response,
                    'json_error' => json_last_error_msg()
                ]);

                return json_encode([
                    "code" => 500,
                    "Erro" => "Erro de formato",
                    "description" => "Resposta não é um JSON válido"
                ]);
            }

            // Verificar se é um erro da API
            if (isset($responseData['error'])) {
                Log::error('ApiEfi - Erro retornado pela API EFI', $responseData);

                return json_encode([
                    "code" => $responseData['error_code'] ?? 400,
                    "Erro" => $responseData['error'],
                    "description" => $responseData['error_description'] ?? 'Erro não especificado'
                ]);
            }

            // Sucesso
            Log::info('ApiEfi - Token obtido com sucesso', [
                'token_type' => $responseData['token_type'] ?? 'N/A',
                'expires_in' => $responseData['expires_in'] ?? 'N/A'
            ]);

            return $responseData['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('ApiEfi - Erro geral ao obter token', [
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

    /**
     * Envia uma cobrança PIX usando cURL direto baseado no exemplo oficial da EFI Pay
     * 
     * @param array $dados - Dados da cobrança PIX
     * @return mixed
     */
    public function enviarPix(array $dados): mixed
    {
        try {
            // Obter token de autenticação
            $tokenResponse = $this->getToken();
            
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
