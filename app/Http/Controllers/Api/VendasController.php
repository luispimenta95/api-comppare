<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use Illuminate\Http\Request;
use App\Models\Cupom;
use App\Http\Util\Payments\MercadoPago;

class VendasController extends MercadoPago
{
    //update server
    private $apiMercadoPago;


    public function __construct()
    {
        $this->apiMercadoPago = new MercadoPago();
    }

    public function index()
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOTKEN_TST'));

        $client = new PaymentClient();
        $request_options = new RequestOptions();
        $request_options->setCustomHeaders(["X-Idempotency-Key:".uniqid()]);

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

        $res = $client->create($createRequest, $request_options);


    }


}
