<?php

namespace App\Http\Util\Payments;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;
use Carbon\Carbon;
use MercadoPago\Preapproval;
use App\Http\Util\Helper;
use App\Models\Usuarios;
use Exception;

class ApiMercadoPago
{
    private $_client;
    private $_options;
    private $payer;

    public function __construct()
    {
        $this->_client = new PreferenceClient();
        $this->_options = new RequestOptions();
        $this->payer = new PaymentClient();
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
        $searchRequest = new MPSearchRequest(30, 0, [
            "sort" => "date_created",
            "criteria" => "desc"
        ]);

        return $this->payer->search($searchRequest);
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

    public function createPlain($nome, $valor)
    {
        $plan = new Preapproval();
        $plan->auto_recurring = [
            "frequency" => 1,
            "frequency_type" => Helper::TIPO_RENOVACAO_MENSAL,
            "transaction_amount" => $valor,
            "currency_id" => Helper::MOEDA,
            "billing_day" => Helper::DIA_COBRANCA,
            "billing_day_proportional" => true,
        ];
        $plan->reason = $nome;
        $plan->back_url = route('updatePayment');
        $plan->status = Helper::STATUS_ATIVO;
        $plan->save();

        return $plan;
    }

    public function createSubscription(Usuarios $usuario): mixed
    {
        // Initialize MercadoPago SDK with access token
        dd(env('ACCESS_TOKEN_TST'));
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        // Create a new Preapproval object for the subscription
        $subscription = new Preapproval();

        // Set subscription details
        $subscription->preapproval_plan_id = 'testeApiLp';
        $subscription->payer_email = $usuario->email;
        $subscription->reason = "Plano de Assinatura Mensal";
        $subscription->external_reference = uniqid();
        $subscription->auto_recurring = array(
            "frequency" => 1, // Frequência do pagamento
            "frequency_type" => Helper::TIPO_RENOVACAO_MENSAL, // Tipo de frequência (meses)
            "transaction_amount" => 29.90, // Valor da assinatura
            "currency_id" => Helper::MOEDA // Moeda
        );

        // Save the subscription (attempt to create the preapproval)
        try {
            $subscription->save();
            return $subscription;
        } catch (Exception $e) {
            // Handle any errors during the save process
            return ['error' => $e->getMessage()];
        }
    }
}
