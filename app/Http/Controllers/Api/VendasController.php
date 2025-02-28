<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\Request;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Common\RequestOptions;
use App\Models\TransacaoFinanceira;

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

    public function recuperarVenda(int $idPagamento)
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        return $this->apiMercadoPago->getPaymentById((int) $idPagamento);
    }

    public function listarVendas()
    {
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        $response = $this->apiMercadoPago->getPayments();
        echo json_encode($response);
    }

    public function updatePayment(): void
    {
        // Captura os parâmetros repassados na URL do redirecionamento
        $orderId = (int) $_GET['collection_id'];
        $orderStatus = $_GET['collection_status'];
        $preferenceId = $_GET['preference_id'];
        $response = $this->recuperarVenda($orderId);
        $pedidio = TransacaoFinanceira::where('idPedido', $preferenceId)->first(); // Obtém o objeto corretamente
        if ($pedidio && strtoupper($orderStatus) == Helper::STATUS_APROVADO) {
            $dadosPagamento = json_decode($pedidio);
            $pedidio->pagamentoEfetuado = true;
            $pedidio->valorFinalPago = $dadosPagamento->valor;
            $pedidio->idUltimoPagamento = $dadosPagamento->id;
            $pedidio->formaPagamento = $dadosPagamento->payment_method;

            $pedidio->save();

            $usuario = Usuarios::find($pedidio->idUsuario);
            $usuario->dataUltimoPagamento = $dadosPagamento->dataPagamento;
            $usuario->idUltimoPagamento  = $orderId;
            $usuario->save();
        }
    }
}
