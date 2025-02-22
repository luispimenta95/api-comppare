<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use Illuminate\Http\Request;
use App\Models\Cupom;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;

// Inicializar chave do Mercado Pago

use App\Http\Util\Payments\ApiMercadoPago;

class VendasController extends Controller
{
    //update server
    private $apiMercadoPago;


    public function __construct()
    {
        $this->apiMercadoPago = new ApiMercadoPago();
    }

    public function realizarVenda()
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOTKEN_TST'));

      $response = $this->apiMercadoPago->salvarVenda();
      echo $response;

    }


}
