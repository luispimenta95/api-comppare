<?php
namespace App\Http\Util\Payments;

use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Client\Payment\PaymentClient;

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
            "additional_info" => [
                "items" => [
                    [
                        "id" => "MLB2907679857",
                        "title" => "Point Mini",
                        "description" => "Point product for card payments via Bluetooth.",
                        "picture_url" => "https://http2.mlstatic.com/resources/frontend/statics/growth-sellers-landings/device-mlb-point-i_medium2x.png",
                        "category_id" => "electronics",
                        "quantity" => 1,
                        "unit_price" => 58,
                        "type" => "electronics",
                        "event_date" => "2023-12-31T09:37:52.000-04:00",
                        "warranty" => false,
                        "category_descriptor" => [
                            "passenger" => [],
                            "route" => []
                        ]
                    ]
                ],
                "payer" => [
                    "first_name" => "Test",
                    "last_name" => "Test",
                    "phone" => [
                        "area_code" => 11,
                        "number" => "987654321"
                    ],
                    "address" => [
                        "street_number" => null
                    ],
                    "shipments" => [
                        "receiver_address" => [
                            "zip_code" => "12312-123",
                            "state_name" => "Rio de Janeiro",
                            "city_name" => "Buzios",
                            "street_name" => "Av das Nacoes Unidas",
                            "street_number" => 3003
                        ],
                        "width" => null,
                        "height" => null
                    ]
                ],
            ],
            "application_fee" => null,
            "binary_mode" => false,
            "campaign_id" => null,
            "capture" => false,
            "coupon_amount" => null,
            "description" => "Payment for product",
            "differential_pricing_id" => null,
            "external_reference" => "MP0001",
            "installments" => 1,
            "metadata" => null,
            "payer" => [
                "entity_type" => "individual",
                "type" => "customer",
                "email" => "test_user_123@testuser.com",
                "identification" => [
                    "type" => "CPF",
                    "number" => "95749019047"
                ]
            ],
            "payment_method_id" => "master",
            "token" => "ff8080814c11e237014c1ff593b57b4d",
            "transaction_amount" => 58,
        ];
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
              'id' => $preference->id,
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
    public function getPaymentById(Request $request)
    {
        MercadoPagoConfig::setAccessToken("ACCESS_TOKEN");

        $client = new PaymentClient();
         return $client->get($request->idPagamento);
    }
}
