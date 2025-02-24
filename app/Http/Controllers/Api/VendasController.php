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

    public function updatePayment():void
    {
        // Captura os parâmetros repassados na URL do redirecionamento
        $collectionId = $_GET['collection_id'];
        $collectionStatus = $_GET['collection_status'];
        $paymentType = $_GET['payment_type'];
        $merchantOrderId = $_GET['merchant_order_id'];
        $externalReference = $_GET['external_reference'];

// Processar os dados do pagamento
        echo "Pagamento ID: " . $collectionId;
        echo "Status do pagamento: " . $collectionStatus;
        echo "Tipo de pagamento: " . $paymentType;
        echo "ID do pedido no Mercado Pago: " . $merchantOrderId;
        echo "Referência externa: " . $externalReference;


    }


}
