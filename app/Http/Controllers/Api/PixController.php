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
        "valorRec": "2.45"
        },
        "chave": "contato@comppare.com.br",
    }',
            CURLOPT_SSLCERT => $this->certificadoPath, // Caminho do certificado
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $this->apiEfi->getToken(),
                "Content-Type: application/json"
            ),
        ));
        $responsePix = json_decode(curl_exec($curl), true);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        // Verificar se a primeira requisição foi bem-sucedida
        if (!$error && ($httpCode === 200 || $httpCode === 201) && isset($responsePix['location']['id'])) {
            $locationId = $responsePix['location']['id'];
            $txid = $responsePix['txid'] ?? '33beb661beda44a8928fef47dbeb2dc5';
            
            Log::info('PIX - Primeira cobrança criada com sucesso', [
                'location_id' => $locationId,
                'txid' => $txid,
                'http_code' => $httpCode
            ]);
            
            // Segunda requisição para v2/locrec
            $curlLocrec = curl_init();
            
            $bodyLocrec = json_encode([
                "ativacao" => [
                    "txid" => $txid
                ]
            ]);
            
            $urlLocrec = $this->enviroment === 'local' 
                ? "https://pix-h.api.efipay.com.br/v2/locrec/{$locationId}"
                : "https://pix.api.efipay.com.br/v2/locrec/{$locationId}";
            
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
            $httpCodeLocrec = curl_getinfo($curlLocrec, CURLINFO_HTTP_CODE);
            $errorLocrec = curl_error($curlLocrec);
            
            curl_close($curlLocrec);
            
            if (!$errorLocrec && ($httpCodeLocrec === 200 || $httpCodeLocrec === 201)) {
                $responseLocrecData = json_decode($responseLocrec, true);
                
                Log::info('PIX - Segunda requisição locrec bem-sucedida', [
                    'location_id' => $locationId,
                    'txid' => $txid,
                    'http_code_locrec' => $httpCodeLocrec,
                    'response_locrec' => $responseLocrecData
                ]);
                
                echo '<h2>✅ PIX Criado com Sucesso!</h2>';
                echo '<h3>Primeira Cobrança (v2/cob):</h3>';
                echo '<pre>' . json_encode($responsePix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
                echo '<h3>Segunda Requisição (v2/locrec):</h3>';
                echo '<pre>' . json_encode($responseLocrecData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
                
            } else {
                Log::error('PIX - Erro na segunda requisição locrec', [
                    'location_id' => $locationId,
                    'txid' => $txid,
                    'error_locrec' => $errorLocrec,
                    'http_code_locrec' => $httpCodeLocrec,
                    'response_locrec' => $responseLocrec
                ]);
                
                echo '<h2>⚠️ PIX Criado, mas erro no locrec</h2>';
                echo '<h3>Primeira Cobrança (Sucesso):</h3>';
                echo '<pre>' . json_encode($responsePix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
                echo '<h3>Erro no locrec:</h3>';
                echo '<p>Erro: ' . $errorLocrec . '</p>';
                echo '<p>HTTP Code: ' . $httpCodeLocrec . '</p>';
                echo '<p>Response: ' . $responseLocrec . '</p>';
            }
            
        } else {
            Log::error('PIX - Erro na primeira requisição', [
                'error' => $error,
                'http_code' => $httpCode,
                'response' => $responsePix
            ]);
            
            echo '<h2>❌ Erro ao Criar PIX</h2>';
            echo '<p>Erro cURL: ' . $error . '</p>';
            echo '<p>HTTP Code: ' . $httpCode . '</p>';
            echo '<pre>' . json_encode($responsePix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
        }
    }

    /**
     * Método principal para envio de PIX - aceita formato oficial EFI Pay
     */
   
}