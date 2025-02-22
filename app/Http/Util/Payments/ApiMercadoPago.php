<?php
namespace App\Http\Util\Payments;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;


class ApiMercadoPago
{
    private $_client;
    private $_options;


    public function __construct()
    {



    }

    public function salvarVenda()
    {
        MercadoPagoConfig::setAccessToken(getenv("ACCESS_TOTKEN_TST"));
        $this->_client = new PreferenceClient();
        $this->_options = new RequestOptions();
        $this->_options->setCustomHeaders(["X-Idempotency-Key: " . uniqid()]);
        $createRequest = [
            "external_reference" => 3,
            "notification_url" => "https://google.com",
            "items"=> array(
                array(
                    "id" => "4567",
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

            return $preference->sandbox_init_point;

        }
        catch (MPApiException $e)
        {

            return $e->getMessage();

        }


    }
}
