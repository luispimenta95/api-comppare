<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use App\Mail\EmailPix;
use App\Mail\EmailPixSimples;
use App\Models\PagamentoPix;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PixController extends Controller
{
    private ApiEfi $apiEfi;
    private string $enviroment;
    private string $certificadoPath;
    private Usuarios $usuario;
    private Planos $plano;
    private string $numeroContrato;


    public function __construct()
    {
        $this->apiEfi = new ApiEfi();
        $this->enviroment = config('app.env'); // Assuming you have an env variable for environment
        $this->certificadoPath = $this->enviroment == "local"
            ? storage_path('app/certificates/hml.pem')
            : storage_path('app/certificates/prd.pem');
    }

    /**
     * Fluxo completo: COB → LOCREC → REC → QRCODE
     */
    public function criarCobranca(Request $request)
    {
        $this ->usuario = Usuarios::find($request->usuario);
        $this ->plano = Planos::find($request->plano);
        $this->numeroContrato = strval(mt_rand(10000000, 99999999)); // Gerando um número de contrato aleatório


        
        // Passo 1: Definir TXID
        $txid = $this->definirTxid();
      
        
        // Passo 2: Criar COB
        $cobResponse = $this->criarCob($txid);
        if (!$cobResponse['success']) {
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao criar COB',
                'error' => $cobResponse['error']
            ], 500);
        }
        
        if (!$cobResponse['success']) {
        }
        
        // Passo 3: Criar Location Rec
        $locrecResponse = $this->criarLocationRec();
        
        if (!$locrecResponse['success']) {
            
        }
        
        $locrecId = $locrecResponse['data']['id'] ?? null;
        if (!$locrecId) {
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao criar Location Rec'
            ], 500);
        }
        
        // Passo 4: Criar REC
        $recResponse = $this->criarRec($txid, $locrecId);
        
        if (!$recResponse['success']) {
            return;
        }

        
        $recId = $recResponse['data']['idRec'] ?? null;
        if (!$recId) {
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao criar REC'
            ], 500);
        }
        
        // Passo 5: Resgatar QR Code
        $qrcodeResponse = $this->resgatarQRCode($recId, $txid);
        $PixCopiaCola = $qrcodeResponse['data']['dadosQR']['pixCopiaECola'] ?? null;
        if(!$PixCopiaCola) {
            return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao resgatar QR Code'
            ], 500);
        }
         try {
            $pagamentoPix = PagamentoPix::create([
                'idUsuario' => $this->usuario->id,
                'txid' => $txid,
                'numeroContrato' => $this->numeroContrato,
                'pixCopiaECola' => $PixCopiaCola,
                'valor' => 2.45,
                'chavePixRecebedor' => 'contato@comppare.com.br',
                'nomeDevedor' =>  $this->usuario->primeiroNome . " " . $this->usuario->sobrenome,
                'cpfDevedor' => $this->usuario->cpf,
                'locationId' => $locrecId,
                'recId' => $recId,
                'status' => 'ATIVA',
                'statusPagamento' => 'PENDENTE',
                'dataInicial' => '2025-07-23',
                'periodicidade' => 'MENSAL',
                'objeto' => $this->plano->nome,
                'responseApiCompleta' => [
                    'cob' => $cobResponse['data'],
                    'locrec' => $locrecResponse['data'],
                    'rec' => $recResponse['data'],
                    'qrcode' => $qrcodeResponse['data']
                ]
            ]);
            if (!$pagamentoPix) {
                return response()->json([
                    'codRetorno' => 500,
                    'message' => 'Erro ao salvar pagamento PIX'
                ], 500);
            }

            Log::info('Pagamento PIX salvo no banco', ['id' => $pagamentoPix->id]);

        } catch (\Exception $e) {
            Log::error('Erro ao salvar pagamento PIX', [
                'error' => $e->getMessage(),
                'txid' => $txid
            ]);
        }

        // Passo 7: Enviar email com o código PIX
        $emailEnviado = false;
        $mensagemEmail = '';
        
        try {
            Log::info('Iniciando envio de email PIX', [
                'email' => $this->usuario->email,
                'txid' => $txid,
                'nome' => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome
            ]);

            // Estrutura correta para BaseEmail
            $dadosParaEmail = [
                'to' => $this->usuario->email,
                'body' => [
                    'nome' => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome,
                    'valor' => 2.45,
                    'pixCopiaECola' => $PixCopiaCola,
                    'contrato' => $this->numeroContrato,
                    'objeto' => $this->plano->nome ?? 'Serviço de Streaming de Música',
                    'periodicidade' => 'MENSAL',
                    'dataInicial' => '2025-07-23',
                    'dataFinal' => null,
                    'txid' => $txid
                ]
            ];

            Log::info('Dados do email PIX preparados', [
                'email_destino' => $dadosParaEmail['to'],
                'dados_corpo' => array_merge($dadosParaEmail['body'], ['pixCopiaECola' => 'OCULTO_POR_SEGURANCA'])
            ]);

            $emailPix = new EmailPix($dadosParaEmail);
            Log::info('Objeto EmailPix criado com sucesso');

            // Enviar o email usando o método correto
            Mail::send($emailPix);
            
            // Se chegou até aqui sem exceção, o email foi enviado com sucesso
            $emailEnviado = true;
            $mensagemEmail = 'Email PIX enviado com sucesso';
            
            Log::info('Email PIX enviado com SUCESSO', [
                'email' => $this->usuario->email,
                'txid' => $txid,
                'status' => 'ENVIADO'
            ]);

        } catch (\Exception $e) {
            $emailEnviado = false;
            $mensagemEmail = 'Erro ao enviar email: ' . $e->getMessage();
            
            Log::error('ERRO ao enviar email PIX', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'email_destino' => $this->usuario->email,
                'txid' => $txid,
                'status' => 'ERRO_ENVIO'
            ]);
        }
        return response()->json([
            'codRetorno' => 200,
            'message' => 'Cobrança PIX criada com sucesso',
            'data' => [
                'pix' => $PixCopiaCola,
                'txid' => $txid,
                'numeroContrato' => $this->numeroContrato
            ],
            'email' => [
                'enviado' => $emailEnviado,
                'status' => $mensagemEmail
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
        $curl = curl_init();
        
        $url = $this->enviroment === 'local' 
            ? "https://pix-h.api.efipay.com.br/v2/cob/{$txid}"
            : "https://pix.api.efipay.com.br/v2/cob/{$txid}";
        
        $body = json_encode([
            "calendario" => [
                "expiracao" => 3600
            ],
            "devedor" => [
                "cpf" => $this->usuario->cpf,
                "nome" => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome
            ],
            "valor" => [
                "original" => "2.45"
            ],
            "chave" => "contato@comppare.com.br"
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
        ]);

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
     * Passo 3: Criar Location Rec - POST /v2/locrec
     */
    private function criarLocationRec(): array
    {
        $curl = curl_init();
        
        $url = $this->enviroment === 'local' 
            ? "https://pix-h.api.efipay.com.br/v2/locrec"
            : "https://pix.api.efipay.com.br/v2/locrec";
        
       

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'success' => !$error && ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'error' => $error,
            'data' => $response ? json_decode($response, true) : null,
            'url' => $url
        ];
    }

    /**
     * Passo 4: Criar REC - POST /v2/rec
     */
    private function criarRec(string $txid, $locrecId): array
    {
        $curl = curl_init();
        
        $url = $this->enviroment === 'local' 
            ? "https://pix-h.api.efipay.com.br/v2/rec"
            : "https://pix.api.efipay.com.br/v2/rec";
        
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
                "dataInicial" => "2025-07-23",
                "periodicidade" => "MENSAL"
            ],
            "valor" => [
                "valorRec" => "2.45"
            ],
            "politicaRetentativa" => "NAO_PERMITE",
            "loc" => $locrecId,
            "ativacao" => [
                "dadosJornada" => [
                    "txid" => $txid
                ]
            ]
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
        ]);

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
     * Passo 5: Resgatar QR Code - GET /v2/rec/{idRec}?txid={txid}
     */
    private function resgatarQRCode(string $recId, string $txid): array
    {
        $curl = curl_init();
        
        $url = $this->enviroment === 'local' 
            ? "https://pix-h.api.efipay.com.br/v2/rec/{$recId}?txid={$txid}"
            : "https://pix.api.efipay.com.br/v2/rec/{$recId}?txid={$txid}";

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ],
        ]);

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
            'body' => null
        ];
    }

    /**
     * Método auxiliar para exibir resultados
     */
  
}
