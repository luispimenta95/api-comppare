<?php

namespace App\Http\Util\Payments;

use App\Models\Planos;
use App\Models\Usuarios;
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
        // URL da API do Mercado Pago
        $url = 'https://api.mercadopago.com/preapproval_plan';

        // Dados do plano de assinatura
        $data = [
            "reason" => $nome,
            "auto_recurring" => [
                "frequency" => 1,
                "frequency_type" => "months",
                "billing_day" => Helper::DIA_COBRANCA,
                "billing_day_proportional" => true,
                //Definir periodo de gratuidade
                "free_trial" => [
                    "frequency" => 15,
                    "frequency_type" => Helper::TIPO_RENOVACAO_DIARIA,
                ],
                "transaction_amount" => $valor,
                "currency_id" => Helper::MOEDA,
            ],
            "payment_methods_allowed" => [
                "payment_types" => [
                    [
                        "id" => "credit_card"
                    ]
                ]
            ],
            "back_url" => "https://www.yoursite.com"
        ];

        // Configurar cabeçalhos da requisição
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token, // Token de acesso
        ];

        // Inicializar cURL
        $ch = curl_init();

        // Configurar parâmetros para a requisição cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Convertendo os dados para JSON
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Executar a requisição e capturar a resposta
        $response = curl_exec($ch);

        // Capturar erros da requisição se houver
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                "error" => true,
                "message" => "Erro na requisição: $error"
            ];
        }

        // Fechar a sessão do cURL
        curl_close($ch);

        // Retornar a resposta decodificada (em array associativo)
        return json_decode($response, true);
    }

    public function createSale(Usuarios $usuario , Planos $plano): mixed
    {
        try {
            // Inicialize o SDK do Mercado Pago
            MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST')); // Use seu token

            // Criar um pagamento para um cliente associado a uma assinatura
            $payment = new Payment();
            $payment->transaction_amount = $plano->valor; // Valor da venda
            $payment->currency_id = Helper::MOEDA; // Moeda (ex: "BRL")
            $payment->description = $plano->nome;
            $payment->payer = [
                'email' => $usuario->email // E-mail do cliente (vinculado ao plano)
            ];

            // Referenciar a assinatura no pagamento
            $payment->external_reference = $plano->idMercadoPago; // ID da assinatura criada

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
