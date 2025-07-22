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

    public function criarCobranca(): void
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
        dd($responsePix);
   $locationId = $responsePix['loc']['id'];
            $txid = $responsePix['txid'];
            
           
            
            // Segunda requisição para v2/locrec
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
            dd($responseLocrec);
        

        /* QR code generation 
        if ($responsePix['loc']['id']) {
            $idlocationPix = $responsePix['loc']['id'];

            // Obtêm o Pix Copia e Cola e QR Code
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL =>  $this->enviroment === 'local' ? 'https://pix-h.api.efipay.com.br/v2/loc/' . $idlocationPix . '/qrcode' : 'https://pix.api.efipay.com.br/v2/loc/' . $idlocationPix . '/qrcode',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_SSLCERT => $this->certificadoPath, // Caminho do certificado
                CURLOPT_SSLCERTPASSWD => "",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . $this->apiEfi->getToken(),
                ),
            ));

            $response = json_decode(curl_exec($curl), true);
        

            curl_close($curl);


            $PixCopiaCola = $response['qrcode'];
            $imagemQrcode = $response['imagemQrcode'];
            */
          
        }
    

    
    public function criarCobrancaRecorrente(): array
{
    $homolog = false;
    $payload = [
        'vinculo' => [
            'contrato' => '63100862',
            'devedor' => [
                "cpf" => "12345678909",
                "nome" => "Francisco da Silva"
            ],
            'objeto' => 'Serviço de Streamming de Música.',
            'dataFinal' => '2025-04-01',
            'dataInicial' => '2024-04-01',
            'periodicidade' => 'MENSAL',
            'valor' => [
                'valorRec' => '2.45'
            ],
            'politicaRetentativa' => 'NAO_PERMITE',
            'loc' => 108,
            'txid' => '33beb661beda44a8928fef47dbeb2dc5'
        ]
    ];
    $urlBase = $homolog
        ? 'https://pix-h.api.efipay.com.br/v2/rec/'
        : 'https://pix.api.efipay.com.br/v2/rec/';

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $urlBase,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSLCERT => $this->certificadoPath,
        CURLOPT_SSLCERTPASSWD => '', // Se houver senha no .pem, coloque aqui
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$this->apiEfi->getToken()}",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $erro = curl_error($curl);

    curl_close($curl);

    if ($erro) {
        throw new \Exception("Erro cURL: " . $erro);
    }

    return json_decode($response, true);
}

}
