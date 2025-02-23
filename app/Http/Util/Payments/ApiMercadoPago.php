<?php
namespace App\Http\Util\Payments;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;
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
        $this->token = MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOTKEN_TST"));

    }

    public function salvarVenda():array
    {
        MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOTKEN_TST"));
        //MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);

        $this->_options->setCustomHeaders(["X-Idempotency-Key: " . uniqid()]);



        $createRequest = [
            "external_reference" => 3,
            "notification_url" => "https://google.com",
            "items"=> array(
                array(
                    "id" => "1789",
                    "title" => "Compras do Carrinho",
                    "description" => "Dummy description",
                    "picture_url" => "http://www.myapp.com/myimage.jpg",
                    "category_id" => "eletronico",
                    "quantity" => 1,
                    "currency_id" => "BRL",
                    "unit_price" => 30.0
                )
            ),
            "default_payment_method_id" => "master",
            "excluded_payment_types" => array(
                array(
                    "id" => "ticket"
                )
            )
        ];

        try
        {

            $preference = $this->_client->create($createRequest);

            return [
              'link' => $preference->init_point,
              'id' => $preference->collector_id
            ] ;


        }
        catch (MPApiException $e)
        {

            return[
                "Erro" =>$e->getMessage()
            ];

        }


    }

    /**
     * @return mixed
     */
    public function getPayments()
    {
        MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOTKEN_TST"));
        $searchRequest = new MPSearchRequest(30, 0, [
            "sort" => "date_created",
            "criteria" => "desc",
            "external_reference" => "ID_REF",
            "range" => "date_created",
            "begin_date" => "NOW-30DAYS",
            "end_date" => "NOW",
            "store_id" => "47792478",
            "pos_id" => "58930090"
        ]);
        $client = new PaymentClient();
        return $client->search($searchRequest);
    }
    public function getPaymentById(int $idPagamento):mixed
    {
        // Configurar o token de acesso do Mercado Pago
        MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOTKEN_TST"));

        // Instanciar o PaymentClient
        $client = new PaymentClient();

        try {
            // Obter os detalhes do pagamento pelo ID
            $payment = $client->get($idPagamento);

            // Retornar informações relevantes do pagamento
            return [
                'status' => $payment->status, // status geral do pagamento (exemplo: "approved", "pending")
                'status_detail' => $payment->status_detail, // detalhes mais específicos do status
                'payment_method' => $payment->payment_method_id, // método de pagamento utilizado
                'id' => $payment->id // ID do pagamento
            ];
        } catch (MPApiException $e) {
            // Em caso de erro, retornar a mensagem
            return [
                "Erro" => $e->getMessage()
            ];
        }
    }
}
