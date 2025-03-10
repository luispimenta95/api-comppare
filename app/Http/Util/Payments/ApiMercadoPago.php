<?php

namespace App\Http\Util\Payments;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;
use Carbon\Carbon;
use App\Http\Util\Helper;

use MercadoPago\Payment;

class ApiMercadoPago
{
    private $_client;
    private $_options;
    private $payer;

    private string $token;
    private string $url = "https://api.mercadopago.com/preapproval_plan";
    public function __construct()
    {
        $this->_client = new PreferenceClient();
        $this->_options = new RequestOptions();
        $this->payer = new PaymentClient();
        $this->token = env('ACCESS_TOKEN_TST'); // Token de teste ou produção

    }

    public function salvarVenda(array $data): mixed
    {

        $this->_options->setCustomHeaders(["X-Idempotency-Key: " . uniqid()]);

        $createRequest = [
            "external_reference" => 3,
            "items" => array(
                array(
                    "id" => $data['id'],
                    "title" => $data['title'],
                    "description" => $data['description'],
                    "picture_url" => "http://www.myapp.com/myimage.jpg",
                    "category_id" => "SERVICES",
                    "quantity" => 1,
                    "currency_id" =>  Helper::MOEDA,
                    "unit_price" => $data['price'],
                )
            ),
            "back_urls" => array(
                "success" => route('updatePayment'),
                "failure" => route('updatePayment'),
                "pending" => route('updatePayment')
            ),
            "auto_return" => "all",
            "default_payment_method_id" => "master",
            "excluded_payment_types" => array(
                array(
                    "id" => "ticket"
                )
            )
        ];

        try {
            $preference = $this->_client->create($createRequest);

            return [
                'link' => $preference->init_point,
                "idPedido" => $preference->id
            ];
        } catch (MPApiException $e) {
            return ["Erro" => $e->getMessage()];
        }
    }

    public function getPayments()
    {
        try {
            $searchRequest = new MPSearchRequest(300, 0, [
                "sort" => "date_created",
                "criteria" => "asc"
            ]);

            return $this->payer->search($searchRequest);
        }catch (MPApiException $e) {
            $response = $e->getApiResponse();
            $statusCode = $e->getStatusCode();

            return [
                "Erro" => "Api error. Check response for details.",
                "Detalhes" => $response ? $response->getContent() : "Nenhuma informação detalhada disponível",
                "Codigo HTTP" => $statusCode
            ];
        }
    }

    public function getPaymentById(int $idPagamento): array
    {
        try {

            $payment = $this->payer->get($idPagamento);

            if (!$payment) {
                return [
                    "Erro" => "Pagamento não encontrado.",
                    "id" => $idPagamento
                ];
            }

            return [
                'status' => $payment->status,
                'detalhe_status' => $payment->status_detail,
                'payment_method' => $payment->payment_method_id,
                'id' => $payment->id,
                'valorFinal' => $payment->transaction_details->total_paid_amount,
                'dataPagamento' => Carbon::parse($payment->date_approved)->format('Y-m-d H:i:s')
            ];
        } catch (MPApiException $e) {
            $response = $e->getApiResponse();
            $statusCode = $e->getStatusCode();

            return [
                "Erro" => "Api error. Check response for details.",
                "Detalhes" => $response ? $response->getContent() : "Nenhuma informação detalhada disponível",
                "Codigo HTTP" => $statusCode
            ];
        }
    }

    function criarPlanoAssinatura(string $nome, float $valor)
    {

        $data = [
            "reason" => $nome, // Motivo da assinatura,
            "payer_email" =>"user@gmail.com",
            "auto_recurring" => [
                "frequency" => 1, // Frequência do ciclo de pagamento
                "frequency_type" => Helper::TIPO_RENOVACAO_MENSAL, // Tipo de frequência: ex.: mensal
                "billing_day" => Helper::DIA_COBRANCA, // Dia de cobrança
                "billing_day_proportional" => true, // Pró-rata para dia de cobrança inicial
                "transaction_amount" => $valor, // Valor da assinatura
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

    public function createSale(string $subscriptionId, float $amount, string $email): mixed
    {
        try {
            // Inicialize o SDK do Mercado Pago
            MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST')); // Use seu token

            // Criar um pagamento para um cliente associado a uma assinatura
            $payment = new Payment();
            $payment->transaction_amount = $amount; // Valor da venda
            $payment->currency_id = 'BRL'; // Moeda (ex: "BRL")
            $payment->description = 'Venda Produto Vinculado';
            $payment->payer = [
                'email' => $email // E-mail do cliente (vinculado ao plano)
            ];

            // Referenciar a assinatura no pagamento
            $payment->external_reference = $subscriptionId; // ID da assinatura criada

            // Salvar o pagamento
            $payment->save();

            // Retornar os detalhes do pagamento realizado
            return $payment;

        } catch (\Exception $e) {
            return [
                'Erro' => 'Erro ao criar a venda vinculada.',
                'Detalhes' => $e->getMessage()
            ];
        }
    }

}
