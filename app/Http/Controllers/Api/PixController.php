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

    public function criarCobranca(): void // Mover chave pix para o arquivo de configuração
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->enviroment === 'local' ? 'https://pix-h.api.efipay.com.br/v2/cob/' : 'https://pix.api.efipay.com.br/v2/cob/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
        "calendario": {
        "expiracao": 3600
        },
        "devedor": {
        "cpf": "12345678909",
        "nome": "Francisco da Silva"
        },
        "valor": {
        "original": "2.45"
        },
        "chave": "contato@comppare.com.br" 
    }',
            CURLOPT_SSLCERT => $this->certificadoPath, // Caminho do certificado
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ),
        ));
        $responsePix = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        $this->createRecurrentCharge($responsePix);
        $this->generateQRCode($responsePix['txid']);

    }
    

    


private function createRecurrentCharge(array $responsePix): void
{
    if (isset($responsePix['loc']['id'])) {
        $locationId = $responsePix['loc']['id'];
        $txid = $responsePix['txid'];
           $curlLocrec = curl_init();

        $bodyLocrec = json_encode([
            "ativacao" => [
                "txid" => $txid
            ]
        ]);

        $urlLocrec = $this->enviroment === 'local'
            ? "https://pix-h.api.efipay.com.br/v2/locrec/{$locationId}?tipoCob=cob"
            : "https://pix.api.efipay.com.br/v2/locrec/{$locationId}?tipoCob=cob";

        curl_setopt_array($curlLocrec, array(
            CURLOPT_URL => $urlLocrec,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $bodyLocrec,
            CURLOPT_SSLCERT => $this->certificadoPath,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ),
        ));

        $responseLocrec = curl_exec($curlLocrec);
        curl_close($curlLocrec);

        // Aqui você pode fazer o que for necessário com o locationId e txid
        Log::info("Location ID: {$locationId}, TXID: {$txid}");
    } else {
        Log::error("Erro ao criar cobrança recorrente: " . json_encode($responsePix));
    }   

}
private function generateQRCode(string $txid): void
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $this->enviroment === 'local' ? "https://pix-h.api.efipay.com.br/v2/loc/{$txid}/qrcode" : "https://pix.api.efipay.com.br/v2/loc/{$txid}/qrcode",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSLCERT => $this->certificadoPath,
        CURLOPT_SSLCERTPASSWD => "",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $this->apiEfi->getToken(),
            "Content-Type: application/json"
        ),
    ));

    $responseQRCode = curl_exec($curl);
    curl_close($curl);
    $PixCopiaCola = $responseQRCode['qrcode'];
    $imagemQrcode = $responseQRCode['imagemQrcode'];
}

    
}

