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

        // Validar se as credenciais foram carregadas
        if (empty($this->options['clientId']) || empty($this->options['clientSecret'])) {
            $error = 'Credenciais EFI Pay não configuradas para ambiente: ' . $this->enviroment;
            Log::error($error, $this->options);
            throw new \Exception($error);
        }

        try {
            Log::info('ApiEfi - Inicializando SDK EFI Pay', [
                'sandbox' => $this->options['sandbox'],
                'timeout' => $this->options['timeout']
            ]);
            
            $this->efiPay = new EfiPay($this->options);
            
            Log::info('ApiEfi - SDK EFI Pay inicializado com sucesso');
        } catch (\Exception $e) {
            Log::error('ApiEfi - Erro ao inicializar SDK EFI Pay', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
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
            dd($this->getToken());

            return json_encode($this->efiPay->createPixRecurrentCharge($this->params, $body));
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

    /**
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
             $certificadoPath = $this->enviroment == "local" 
                ?  storage_path('app/certificates/hml.pem')
                :  storage_path('app/certificates/prd.pem');
            $baseUrl = $this->enviroment == "local" ? 
                "https://pix-h.api.efipay.com.br" : 
                "https://pix.api.efipay.com.br";

                //dd(is_file($certificadoPath), $certificadoPath, file_exists($certificadoPath));
            
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
                CURLOPT_POSTFIELDS => '{"grant_type": "client_credentials"}',
                CURLOPT_SSLCERT => $certificadoPath,
                CURLOPT_SSLCERTPASSWD => '',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Basic $autorizacao",
                    "Content-Type: application/json"
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            
            // Adicionar certificado se fornecido
            if (!empty($certificado) && file_exists($certificado)) {
                $curlOptions[CURLOPT_SSLCERT] = $certificado;
                $curlOptions[CURLOPT_SSLCERTPASSWD] = env('EFI_CERTIFICADO_PASSWORD', '');
                Log::info('ApiEfi - Usando certificado SSL', ['certificado' => $certificado]);
            }
            
            curl_setopt_array($curl, $curlOptions);
            
            Log::info('ApiEfi - Executando requisição para obter token', [
                'url' => $baseUrl . "/oauth/token",
                'environment' => $this->enviroment
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
            
            return $response;
            
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
}
