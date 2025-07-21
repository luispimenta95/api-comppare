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

  public function criarCobrancaPixSimples(array $payload, bool $homolog = false): array
{
    $url = $homolog
        ? 'https://pix-h.api.efipay.com.br/v2/cob/'
        : 'https://pix.api.efipay.com.br/v2/cob/';

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSLCERT => $this->certificadoPath,
        CURLOPT_SSLCERTPASSWD => '',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $this->apiEfi->getToken(),
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($curl);
    $erro = curl_error($curl);

    curl_close($curl);

    if ($erro) {
        throw new \Exception("Erro ao criar cobrança: $erro");
    }

    return json_decode($response, true);
}

public function consultarQrCodePorLocId(int $idLoc, bool $homolog = false): array
{
    $url = $homolog
        ? "https://pix-h.api.efipay.com.br/v2/loc/{$idLoc}/qrcode"
        : "https://pix.api.efipay.com.br/v2/loc/{$idLoc}/qrcode";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSLCERT => $this->certificadoPath,
        CURLOPT_SSLCERTPASSWD => '',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $this->apiEfi->getToken()
        ],
    ]);

    $response = curl_exec($curl);
    $erro = curl_error($curl);

    curl_close($curl);

    if ($erro) {
        throw new \Exception("Erro ao consultar QR Code: $erro");
    }

    return json_decode($response, true);
}
public function criarCobranca(){
   $payload = [
        'vinculo' => [
            'contrato' => '63100862',
            'devedor' => [
                'cpf' => '45164632481',
                'nome' => 'Fulano de Tal'
            ],
            'objeto' => 'Serviço de Streamming de Música.',
            'dataFinal' => '2025-04-01',
            'dataInicial' => '2024-04-01',
            'periodicidade' => 'MENSAL',
            'valor' => 35.00,
            'politicaRetentativa' => 'NAO_PERMITE',
            'loc' => 108,
            'txid' => '33beb661beda44a8928fef47dbeb2dc5'
        ]
    ];

try {
    $res = $this->criarCobrancaPixSimples($payload, true);

    echo '<pre>' . json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';

    if (isset($res['loc']['id'])) {
        $qrcode = $this->consultarQrCodePorLocId($res['loc']['id'], true);

        echo 'QR Code Copia e Cola:<br><pre>' . $qrcode['qrcode'] . '</pre>';
        echo 'Imagem:<br><img src="' . $qrcode['imagemQrcode'] . '" />';
    }

} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
}

}
