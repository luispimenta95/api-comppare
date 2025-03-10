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
use MercadoPago\Preapproval;
use MercadoPago\Payment;

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
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST')); // Token de teste ou produção

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
            $searchRequest = new MPSearchRequest(3, 0, [
                "sort" => "date_created",
                "criteria" => "desc"
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

    public function criarPlano($nome, $valor)
    {
        try {
            // Criar um novo plano de assinatura
            $subscription = new Preapproval();
            $subscription->auto_recurring = [
                'frequency' => 1, // Frequência do pagamento (ex: 1)
                'frequency_type' => 'months', // Tipo de frequência (ex: "months")
                'transaction_amount' => 45.90, // Valor do plano
                'currency_id' => 'BRL', // Moeda (ex: "BRL")
                'start_date' => date('Y-m-d\TH:i:sP', strtotime('+1 day')), // Data de início do plano
                'end_date' => date('Y-m-d\TH:i:sP', strtotime('+2 year')), // Data de término do plano
            ];
            $subscription->payer_email = 'cliente@email.com'; // E-mail do comprador
            $subscription->reason = 'Assinatura Mensal'; // Motivo do pagamento
            $subscription->external_reference = uniqid(); // Referência externa para controle interno

            // Salvar o plano de assinatura
            $subscription->save();

            // Retornar os detalhes do plano de assinatura criado
            return [
                'id' => $subscription->id,
                'init_point' => $subscription->init_point // URL para iniciar a assinatura
            ];

        } catch (\Exception $e) {
            return [
                'Erro' => 'Erro ao criar o plano de assinatura.',
                'Detalhes' => $e->getMessage()
            ];
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
