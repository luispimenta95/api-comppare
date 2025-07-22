<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Http\Util\Payments\ApiEfi;
use App\Models\PagamentoPix;
use App\Models\Usuarios;
use App\Mail\EmailPix;
use App\Models\Planos;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Fluxo completo: COB → LOCREC → REC → QRCODE → SAVE → EMAIL
     */
    public function criarCobranca(Request $request)
    {
        // Dados do usuário (pode vir da request ou usar padrão para teste)
        $campos = ['usuario', 'plano'];
        $campos = Helper::validarRequest($request, $campos);
        
        $usuario = Usuarios::find($request->usuario);
        $plano = Planos::find($request->plano);
        // Passo 1: Definir TXID
        $txid = $this->definirTxid();
        $numeroContrato = strval(mt_rand(10000000, 99999999));
      
        // Passo 2: Criar COB
        $cobResponse = $this->criarCob($txid , $usuario, $plano);
        
        if (!$cobResponse['success']) {
            Log::error('Falha na criação da COB', $cobResponse);
            return null;
        }
        
        // Passo 3: Criar Location Rec
        $locrecResponse = $this->criarLocationRec();
        
        if (!$locrecResponse['success']) {
            Log::error('Falha na criação do Location Rec', $locrecResponse);
            return null;
        }
        
        $locrecId = $locrecResponse['data']['id'] ?? null;
        if (!$locrecId) {
            Log::error('ID do Location Rec não encontrado');
            return null;
        }
        
        // Passo 4: Criar REC
        $recResponse = $this->criarRec($txid, $locrecId, $usuario, $plano);
        
        if (!$recResponse['success']) {
            Log::error('Falha na criação da REC', $recResponse);
            return null;
        }
        
        $recId = $recResponse['data']['id'] ?? null;
        if (!$recId) {
            Log::error('ID da REC não encontrado');
            return null;
        }
        
        // Passo 5: Resgatar QR Code
        $qrcodeResponse = $this->resgatarQRCode($recId, $txid);
        $pixCopiaECola = $qrcodeResponse['data']['dadosQR']['pixCopiaECola'] ?? null;
        
        if (!$pixCopiaECola) {
            Log::error('PIX Copia e Cola não encontrado', $qrcodeResponse);
            return null;
        }

        // Passo 6: Salvar no banco de dados
        try {
            $pagamentoPix = PagamentoPix::create([
                'idUsuario' => $usuario->id,
                'txid' => $txid,
                'numeroContrato' => $numeroContrato,
                'pixCopiaECola' => $pixCopiaECola,
                'valor' => 2.45,
                'chavePixRecebedor' => 'contato@comppare.com.br',
                'nomeDevedor' =>  $usuario->primeiroNome . " " . $usuario->sobrenome,
                'cpfDevedor' => $usuario->cpf,
                'locationId' => $locrecId,
                'recId' => $recId,
                'status' => 'ATIVA',
                'statusPagamento' => 'PENDENTE',
                'dataInicial' => '2025-07-23',
                'periodicidade' => 'MENSAL',
                'objeto' => $plano->nome,
                'responseApiCompleta' => [
                    'cob' => $cobResponse['data'],
                    'locrec' => $locrecResponse['data'],
                    'rec' => $recResponse['data'],
                    'qrcode' => $qrcodeResponse['data']
                ]
            ]);

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
                'nome' => $usuario->primeiroNome . " " . $usuario->sobrenome,
                'valor' => 2.45,
                'pixCopiaECola' => $pixCopiaECola,
                'contrato' => $numeroContrato,
                'objeto' => 'Serviço de Streamming de Música.',
                'periodicidade' => 'MENSAL',
                'dataInicial' => '2025-07-23',
                'dataFinal' => null,
                'txid' => $txid
            ];

            Mail::to($usuario->email)->send(new EmailPix($dadosEmail));
            
            Log::info('Email PIX enviado com sucesso', [
                'email' => $usuario->email,
                'txid' => $txid
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao enviar email PIX', [
                'error' => $e->getMessage(),
                'email' => $usuario->email,
                'txid' => $txid
            ]);
        }

        return $pixCopiaECola;
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
    private function criarCob(string $txid, $usuario, $plano): array
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
                "cpf" => $usuario->cpf,
                "nome" => $usuario->primeiroNome . " " . $usuario->sobrenome
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
    private function criarRec(string $txid, $locrecId, $usuario, $plano): array
    {
        $curl = curl_init();
        
        $url = $this->enviroment === 'local' 
            ? "https://pix-h.api.efipay.com.br/v2/rec"
            : "https://pix.api.efipay.com.br/v2/rec";
        
        $body = json_encode([
            "vinculo" => [
                "contrato" => strval(mt_rand(10000000, 99999999)),
                "devedor" => [
                    "cpf" => $usuario->cpf,
                    "nome" => $usuario->primeiroNome . " " . $usuario->sobrenome
                ],
                "objeto" => $plano->nome
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
