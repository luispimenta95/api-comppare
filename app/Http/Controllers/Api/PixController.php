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
        var_dump($cobResponse);
        
        if (!$cobResponse['success']) {
        }
        
        // Passo 3: Criar Location Rec
        $locrecResponse = $this->criarLocationRec();
        var_dump($locrecResponse);
        
        if (!$locrecResponse['success']) {
            
        }
        
        $locrecId = $locrecResponse['data']['id'] ?? null;
        if (!$locrecId) {
            return;
        }
        
        // Passo 4: Criar REC
        $recResponse = $this->criarRec($txid, $locrecId);
        var_dump($recResponse);
        
         // Verifica se a criação do REC foi bem-sucedida
        
        if (!$recResponse['success']) {
            return;
        }

        
        $recId = $recResponse['data']['idRec'] ?? null;
        if (!$recId) {
            return;
        }
        
        // Passo 5: Resgatar QR Code
        $qrcodeResponse = $this->resgatarQRCode($recId, $txid);
        var_dump($qrcodeResponse);
        $PixCopiaCola = $qrcodeResponse['data']['dadosQR']['pixCopiaECola'] ?? null;
        var_dump($PixCopiaCola);
        return $PixCopiaCola;


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
                "dataInicial" => date('Y-m-d'),
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