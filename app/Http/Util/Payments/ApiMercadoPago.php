<?php

namespace App\Http\Util\Payments;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;
use Carbon\Carbon;
use App\Http\Util\Helper;
use App\Models\Usuarios;
use Exception;
use MercadoPago\Preapproval;

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
        // Initialize MercadoPago SDK with the access token
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        // Prepare subscription data
        $subscriptionData = [
            'payer_email' => $usuario->email,
            'reason' => 'Plano de Assinatura Mensal',
            'back_url' => route('updatePaymentSubscription'), // URL de retorno
            'auto_return' => 'all', // Se a assinatura for confirmada, retornar para esta URL
            'status' => Helper::STATUS_ATIVO,
            'external_reference' => uniqid(),
            'auto_recurring' => [
                'frequency' => 1, // Frequência do pagamento
                'frequency_type' => Helper::TIPO_RENOVACAO_MENSAL, // Tipo de frequência (meses)
                'transaction_amount' => 45.90, // Valor da assinatura
                'currency_id' => Helper::MOEDA, // Moeda
            ]
        ];

        try {
            // Create a new Preapproval object for the subscription
            $preapproval = new Preapproval();

            // Add subscription data to the Preapproval object
            foreach ($subscriptionData as $key => $value) {
                $preapproval->$key = $value;
            }

            // Save the subscription
            $preapproval->save();

            // Return the created subscription data or confirmation
            return $preapproval;
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            // Handle API exceptions
            $response = $e->getApiResponse();
            $statusCode = $e->getStatusCode();

            return [
                "Erro" => "Api error. Check response for details.",
                "Detalhes" => $response ? $response->getContent() : "Nenhuma informação detalhada disponível",
                "Codigo HTTP" => $statusCode
            ];
        }
    }
    }
