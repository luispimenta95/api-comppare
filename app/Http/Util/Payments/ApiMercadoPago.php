<?php
namespace App\Http\Util\Payments;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Preference;

//fix types
class ApiMercadoPago
{
    private $_client;
    private $_options;
    private $config;

    public function __construct()
    {
        $this->_client = new PreferenceClient();
        $this->_options = new RequestOptions();

    }

public function salvarVenda(Array $data): mixed
{
    MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOKEN_TST"));

    $this->_options->setCustomHeaders(["X-Idempotency-Key: " . uniqid()]);



    $createRequest = [
        "external_reference" => 3,
        "notification_url" => "https://google.com",
        "items"=> array(
            array(
                "id" => $data['id'],
                "title" => $data['title'],
                "description" => $data['description'],
                "picture_url" => "http://www.myapp.com/myimage.jpg",
                "category_id" => "SERVICES",
                "quantity" => 1,
                "currency_id" => "BRL",
                "unit_price" => $data['price'],
            )
        ),
        "back_urls" => array(
            "success" => "https://api.comppare.com.br/api/vendas/update-payment",
            "failure" => "https://api.comppare.com.br/api/vendas/update-payment",
            "pending" => "https://api.comppare.com.br/api/vendas/update-payment"
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
        $preferenceJson = json_encode($preference, JSON_PRETTY_PRINT);

// Nome do arquivo onde o conteúdo será salvo
        $filePath = storage_path('app/preference_log.txt'); // Diretório seguro no Laravel

// Salvar o JSON no arquivo
        file_put_contents($filePath, $preferenceJson);

        return [
            'link' => $preference->init_point,
            "idPagamento" => $preference->id
        ];
    } catch (MPApiException $e) {
        return ["Erro" => $e->getMessage()];
    }
}

    public function getPayments()
    {
        MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOKEN_TST"));

        $searchRequest = new MPSearchRequest(30, 0, [
            "sort" => "date_created",
            "criteria" => "desc"
            ]);

        $client = new PaymentClient();
        return $client->search($searchRequest);
    }

    public function getPaymentById(int $idPagamento): array
    {
        try {
            MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOKEN_TST"));
            $client = new PaymentClient();
            $payment = $client->get($idPagamento);

            if (!$payment) {
                return [
                    "Erro" => "Pagamento não encontrado.",
                    "id" => $idPagamento
                ];
            }

            return [
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'payment_method' => $payment->payment_method_id,
                'id' => $payment->id
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
}
