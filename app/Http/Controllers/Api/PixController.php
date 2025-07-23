<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use App\Mail\EmailPix;
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
        try {
            $dadosEmail = [
                'to' => $this->usuario->email,
                'nome' => $this->usuario->primeiroNome . " " . $this->usuario->sobrenome,
                'valor' => 2.45,
                'pix' => $PixCopiaCola,
                'contrato' => $this->numeroContrato,
                'objeto' => 'Serviço de Streamming de Música.',
                'periodicidade' => 'MENSAL',
                'dataInicial' => '2025-07-23',
                'dataFinal' => null,
                'txid' => $txid
            ];

            Mail::to($this->usuario->email)->send(new EmailPix($dadosEmail));
            

            Log::info('Email PIX enviado com sucesso', [
                'email' => $this->usuario->email,
                'txid' => $txid
            ]);

        } catch (\Exception $e) {
          return response()->json([
                'codRetorno' => 500,
                'message' => 'Erro ao enviar e-mail PIX',
                'error' => $e->getMessage()
            ], 500);
        }
        return response()->json([
            'codRetorno' => 200,
            'message' => 'Cobrança PIX criada com sucesso',
            'data' => [
                'txid' => $txid,
                'pixCopiaECola' => $PixCopiaCola,
                'recId' => $recId
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
