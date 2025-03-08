<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Planos;
use App\Models\Usuarios;
use App\Http\Util\Helper;
use MercadoPago\MercadoPagoConfig;
use App\Models\TransacaoFinanceira;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Util\MailHelper;


// Inicializar chave do Mercado Pago

use App\Http\Util\Payments\ApiMercadoPago;

class VendasController extends Controller
{
    //update server
    private $apiMercadoPago;
    private array $codes = [];


    public function __construct()
    {
        $this->apiMercadoPago = new ApiMercadoPago();
        $this->codes = Helper::getHttpCodes();
    }

    public function realizarVenda(Planos $plano): mixed
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

    public function recuperarVenda(int $idPagamento): array
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

    public function updatePayment(): JsonResponse
    {
        // Captura os parâmetros repassados na URL do redirecionamento
        $orderId = (int) $_GET['collection_id'];
        $orderStatus = $_GET['collection_status'];
        $preferenceId = $_GET['preference_id'];
        $responseApi = $this->recuperarVenda($orderId);
        $pedidio = TransacaoFinanceira::where('idPedido', $preferenceId)->first(); // Obtém o objeto corretamente

        if ($pedidio && strtoupper($orderStatus) !== Helper::STATUS_APROVADO) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-10]
            ];

            return response()->json($response);
        }

        $pedidio->pagamentoEfetuado = true;
        $pedidio->valorFinalPago = $responseApi['valorFinal'];
        $pedidio->idPagamento = $responseApi['id'];
        $pedidio->formaPagamento = $responseApi['payment_method'];

        $pedidio->save();

        $usuario = Usuarios::find($pedidio->idUsuario);
        $usuario->dataUltimoPagamento = $responseApi['dataPagamento'];
        $usuario->idUltimoPagamento  = $orderId;
        $usuario->dataLimiteCompra =  Carbon::parse($usuario->dataUltimoPagamento)->addDays(Helper::TEMPO_RENOVACAO)->format('Y-m-d H:i:s');
        $usuario->save();
        $dadosEmail = [
            'nome' => $usuario->nome,
            'dataRenovacao' =>  $usuario->dataLimiteCompra
        ];

        MailHelper::confirmacaoPagamento($dadosEmail, $usuario->email);

        $response = [
            'codRetorno' => 200,
            'message' => 'Pagamento do pedido ' . $orderId . 'foi atualizado com sucesso.'
        ];

        // Redirecionar o cliente para a página de sucesso da venda
        return response()->json($response);
    }

    public function createSubscription(): JsonResponse
    {
        $usuario = Usuarios::find(1);
        $responseApi = $this->apiMercadoPago->createSubscription($usuario);
        return response()->json($responseApi);
    }
}
