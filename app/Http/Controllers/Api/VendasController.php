<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Planos;
use App\Models\Usuarios;
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

    public function realizarVenda(Usuarios $usuario, Planos $plano)
    {
        $data = [
            "id" => $plano->id,
            "title" => $plano->nome,
            "description" => $plano->descricao,
            "price" => $plano->valor
        ];
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

      return $this->apiMercadoPago->salvarVenda($data);

    }

    public function recuperarVenda(Request $request)
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        $response = $this->apiMercadoPago->getPaymentById((int) $request->idPagamento) ;
        echo json_encode($response);

    }

    public function listarVendas()
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        $response = $this->apiMercadoPago->getPayments();
        echo json_encode($response);

    }


}
