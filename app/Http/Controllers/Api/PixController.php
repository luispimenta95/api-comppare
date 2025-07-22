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
    echo '<h2>🔄 2. CONSULTA LOCATION RECORRENTE (v2/locrec)</h2>';
    
    if (isset($responsePix['txid'])) {
        $txid = $responsePix['txid'];
        $curlLocrec = curl_init();

        $urlLocrec = $this->enviroment === 'local'
            ? "https://pix-h.api.efipay.com.br/v2/locrec/{$txid}"
            : "https://pix.api.efipay.com.br/v2/locrec/{$txid}";

        echo '<p><strong>URL:</strong> ' . $urlLocrec . '</p>';
        echo '<p><strong>TXID:</strong> ' . $txid . '</p>';

        curl_setopt_array($curlLocrec, array(
            CURLOPT_URL => $urlLocrec,
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

        $responseLocrec = curl_exec($curlLocrec);
        $httpCode = curl_getinfo($curlLocrec, CURLINFO_HTTP_CODE);
        $error = curl_error($curlLocrec);
        curl_close($curlLocrec);

        if ($responseLocrec) {
            $responseLocrecData = json_decode($responseLocrec, true);
        }

        echo '<p><strong>HTTP Code:</strong> ' . $httpCode . '</p>';
        echo '<p><strong>Erro cURL:</strong> ' . ($error ?? 'Nenhum') . '</p>';
        echo '<p><strong>Resposta:</strong></p>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px;">' . 
             json_encode($responseLocrecData ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . 
             '</pre>';

        if (!$error && ($httpCode === 200 || $httpCode === 201)) {
            echo '<p style="color: green;">✅ <strong>Consulta locrec realizada com sucesso!</strong></p>';
            Log::info("Consulta locrec bem-sucedida para TXID: {$txid}", $responseLocrecData ?? []);
        } else {
            echo '<p style="color: red;">❌ <strong>Erro na consulta locrec</strong></p>';
            Log::error("Erro na consulta locrec para TXID: {$txid}", [
                'error' => $error,
                'http_code' => $httpCode,
                'response' => $responseLocrecData ?? null
            ]);
        }
    } else {
        echo '<p style="color: red;">❌ <strong>TXID não encontrado na resposta PIX</strong></p>';
        echo '<p>Resposta recebida:</p>';
        echo '<pre style="background: #ffe6e6; padding: 10px; border-radius: 5px;">' . 
             json_encode($responsePix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . 
             '</pre>';
        Log::error("TXID não encontrado na resposta PIX: " . json_encode($responsePix));
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
