<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PixController extends Controller
{
    private ApiEfi $apiEfi;

    public function __construct()
    {
        $this->apiEfi = new ApiEfi();
    }

    /**
     * Cria uma cobrança PIX recorrente
     * 
     * Exemplo de uso:
     * POST /api/pix/recorrente
     * {
     *   "contrato": "63100862",
     *   "devedor": {
     *     "cpf": "45164632481",
     *     "nome": "Fulano de Tal"
     *   },
     *   "objeto": "Serviço de Streamming de Música",
     *   "dataFinal": "2025-04-01",
     *   "dataInicial": "2024-04-01",
     *   "periodicidade": "MENSAL",
     *   "valor": "35.00",
     *   "politicaRetentativa": "NAO_PERMITE",
     *   "loc": 108,
     *   "txid": "33beb661beda44a8928fef47dbeb2dc5"
     * }
     */
    public function createRecurrentCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contrato' => 'required|string',
            'devedor.cpf' => 'required|string|size:11',
            'devedor.nome' => 'required|string|max:255',
            'objeto' => 'nullable|string|max:255',
            'dataFinal' => 'required|date_format:Y-m-d',
            'dataInicial' => 'required|date_format:Y-m-d',
            'periodicidade' => 'nullable|string|in:MENSAL,SEMANAL,ANUAL',
            'valor' => 'required|numeric|min:0.01',
            'politicaRetentativa' => 'nullable|string|in:PERMITE,NAO_PERMITE',
            'loc' => 'nullable|integer',
            'txid' => 'nullable|string'
        ]);

        $result = $this->apiEfi->createPixRecurrentCharge($validated);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar cobrança PIX recorrente',
                'error' => $response
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cobrança PIX recorrente criada com sucesso',
            'data' => $response
        ]);
    }

    /**
     * Cria uma cobrança PIX com QR Code dinâmico
     * 
     * Exemplo de uso:
     * POST /api/pix/dinamico
     * {
     *   "devedor": {
     *     "cpf": "45164632481",
     *     "nome": "Fulano de Tal"
     *   },
     *   "valor": 35.00,
     *   "descricao": "Pagamento de assinatura",
     *   "expiracao": 3600,
     *   "chave_pix": "sua_chave_pix@email.com"
     * }
     */
    public function createDynamicCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'devedor.cpf' => 'required|string|size:11',
            'devedor.nome' => 'required|string|max:255',
            'valor' => 'required|numeric|min:0.01',
            'descricao' => 'nullable|string|max:255',
            'expiracao' => 'nullable|integer|min:1',
            'chave_pix' => 'nullable|string',
            'infoAdicionais' => 'nullable|array'
        ]);

        $result = $this->apiEfi->createPixDynamicCharge($validated);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar cobrança PIX dinâmica',
                'error' => $response
            ], 400);
        }

        // Se a cobrança foi criada com sucesso, gerar o QR Code
        if (isset($response['txid'])) {
            $qrCodeResult = $this->apiEfi->generatePixQRCode($response['txid']);
            $qrCodeResponse = json_decode($qrCodeResult, true);
            
            if (!isset($qrCodeResponse['code'])) {
                $response['qr_code'] = $qrCodeResponse;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cobrança PIX dinâmica criada com sucesso',
            'data' => $response
        ]);
    }

    /**
     * Gera QR Code para uma cobrança PIX existente
     * 
     * GET /api/pix/qrcode/{txid}
     */
    public function generateQRCode(string $txid): JsonResponse
    {
        $result = $this->apiEfi->generatePixQRCode($txid);
        $response = json_decode($result, true);

        if (isset($response['code']) && $response['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar QR Code',
                'error' => $response
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR Code gerado com sucesso',
            'data' => $response
        ]);
    }
}
