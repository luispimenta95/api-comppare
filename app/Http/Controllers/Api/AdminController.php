<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use Illuminate\Http\Request;


class AdminController extends Controller
{
    private array $codes = [];
    private string $token;
    private  string  $url = 'https://api.mercadopago.com/preapproval_plan';

    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->token = env('ACCESS_TOKEN_TST');

    }
    function criarPlanoAssinatura(Request $request)
    {
        $campos = ['plano', 'valor'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $data = [
            "reason" => $request->plano, // Motivo da assinatura
            "auto_recurring" => [
                "frequency" => 1, // Frequência do ciclo de pagamento
                "frequency_type" => Helper::TIPO_RENOVACAO_MENSAL, // Tipo de frequência: ex.: mensal
                "billing_day" => Helper::DIA_COBRANCA, // Dia de cobrança
                "billing_day_proportional" => true, // Pró-rata para dia de cobrança inicial
                "transaction_amount" => $request->valor, // Valor da assinatura
                "currency_id" => Helper::MOEDA// Moeda: Real
            ],
            "payment_methods_allowed" => [
                "payment_types" => [
                    [ "id" => "credit_card" ] // Aceitar cartões de crédito
                ]
            ],
            "back_url" => env('APP_URL') . "api/vendas/update-payment-subscription" // URL de retorno ao finalizar assinatura
        ];

        $headers = [
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/json"
        ];

        // Inicia cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Dados em formato JSON
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retornar resposta
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Adiciona os headers

        // Executa a requisição
        $response = curl_exec($ch);

        // Captura erros HTTP
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return ["error" => "Erro na requisição: " . $error_msg];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Código HTTP de retorno
        curl_close($ch);

        // Decodifica resposta JSON
        $decodedResponse = json_decode($response, true);

        // Retorna a resposta de forma adequada
        if ($httpCode === 201) {
            return $decodedResponse; // Plano criado com sucesso
        } else {
            return [
                "error" => "Erro ao criar plano",
                "status" => $httpCode,
                "response" => $decodedResponse
            ]; // Retorna detalhes do erro
        }
    }

}
