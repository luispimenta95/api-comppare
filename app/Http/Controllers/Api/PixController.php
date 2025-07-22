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

    /**
     * Fluxo completo: COB → LOCREC → REC → QRCODE
     */
    public function criarCobranca(): void
    {
        echo '<h1>🚀 FLUXO COMPLETO PIX RECORRENTE</h1>';
        echo '<hr>';
        
        // Passo 1: Definir TXID
        $txid = $this->definirTxid();
        echo '<h2>1️⃣ TXID DEFINIDO</h2>';
        echo '<p><strong>TXID:</strong> ' . $txid . '</p>';
        echo '<hr>';
        
        // Passo 2: Criar COB
        $cobResponse = $this->criarCob($txid);
        $this->exibirResultado('2️⃣ CRIAR COB (PUT /v2/cob/:txid)', $cobResponse);
        
        if (!$cobResponse['success']) {
            echo '<p style="color: red;">❌ Falha na criação da COB. Processo interrompido.</p>';
            return;
        }
        
        // Passo 3: Criar Location Rec
        $locrecResponse = $this->criarLocationRec();
        $this->exibirResultado('3️⃣ CRIAR LOCATION REC (POST /v2/locrec)', $locrecResponse);
        
        if (!$locrecResponse['success']) {
            echo '<p style="color: red;">❌ Falha na criação do Location Rec. Processo interrompido.</p>';
            return;
        }
        
        $locrecId = $locrecResponse['data']['id'] ?? null;
        if (!$locrecId) {
            echo '<p style="color: red;">❌ ID do Location Rec não encontrado. Processo interrompido.</p>';
            return;
        }
        
        // Passo 4: Criar REC
        $recResponse = $this->criarRec($txid, $locrecId);
        $this->exibirResultado('4️⃣ CRIAR REC (POST /v2/rec)', $recResponse);
        
        if (!$recResponse['success']) {
            echo '<p style="color: red;">❌ Falha na criação da REC. Processo interrompido.</p>';
            return;
        }
        
        $recId = $recResponse['data']['id'] ?? null;
        if (!$recId) {
            echo '<p style="color: red;">❌ ID da REC não encontrado. Processo interrompido.</p>';
            return;
        }
        
        // Passo 5: Resgatar QR Code
        $qrcodeResponse = $this->resgatarQRCode($recId, $txid);
        $this->exibirResultado('5️⃣ RESGATAR QRCODE (GET /v2/rec/{idRec}?txid={txid})', $qrcodeResponse);
        
        echo '<hr>';
        echo '<h2>🎉 PROCESSO FINALIZADO</h2>';
        echo '<p><em>Teste concluído em ' . date('d/m/Y H:i:s') . '</em></p>';
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
                "cpf" => "02342288140",
                "nome" => "Fulano"
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
                "contrato" => "63100862",
                "devedor" => [
                    "cpf" => "02342288140",
                    "nome" => "Fulano"
                ],
                "objeto" => "Serviço de Streamming de Música."
            ],
            "calendario" => [
                "dataInicial" => "2025-07-23",
                "periodicidade" => "MENSAL"
            ],
            "valor" => [
                "valorRec" => "35.00"
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
    private function exibirResultado(string $titulo, array $resultado): void
    {
        echo '<h2>' . $titulo . '</h2>';
        echo '<p><strong>URL:</strong> ' . $resultado['url'] . '</p>';
        echo '<p><strong>HTTP Code:</strong> ' . $resultado['http_code'] . '</p>';
        echo '<p><strong>Erro cURL:</strong> ' . ($resultado['error'] ?? 'Nenhum') . '</p>';
        
        if (isset($resultado['body'])) {
            echo '<p><strong>Body enviado:</strong></p>';
            echo '<pre style="background: #e8f4f8; padding: 10px; border-radius: 5px;">' . 
                 $resultado['body'] . 
                 '</pre>';
        }
        
        echo '<p><strong>Resposta:</strong></p>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 5px;">' . 
             json_encode($resultado['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . 
             '</pre>';
        
        if ($resultado['success']) {
            echo '<p style="color: green;">✅ <strong>Sucesso!</strong></p>';
        } else {
            echo '<p style="color: red;">❌ <strong>Erro!</strong></p>';
        }
        
        echo '<hr>';
    }
}
