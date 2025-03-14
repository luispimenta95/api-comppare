<?php

namespace App\Http\Controllers\Api;

use App\Http\Util\Payments\ApiEfi;
use Carbon\Carbon;
use App\Models\Planos;
use App\Models\Usuarios;
use App\Http\Util\Helper;
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
    private ApiEfi $apiEfi;


    public function __construct()
    {
        $this->apiMercadoPago = new ApiMercadoPago();
        $this->codes = Helper::getHttpCodes();
        $this->apiEfi = new ApiEfi();

    }

    public function realizarVenda(Planos $plano): mixed
    {
        $data = [
            "id" => $plano->id,
            "title" => $plano->nome,
            "description" => $plano->descricao,
            "price" => $plano->valor
        ];

        return $this->apiMercadoPago->salvarVenda($data);
    }

    public function recuperarVenda(int $idPagamento): array
    {

        return $this->apiMercadoPago->getPaymentById((int) $idPagamento);
    }

    public function listarVendas()
    {

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

    public function createSubscription()
    {
        $usuario = Usuarios::find(1);

        $data = [
            "cardToken" => "630b5ac1b28b3be4dca5fde9052e63b55fa71ffa",
            "idPlano" =>1,
            "usuario" => [
                "name" => "Gorbadoc Oldbuck",
                "cpf" => "04267484171",
                "phone_number" => "5144916523",
                "email" => "oldbuck@server.com.br",
                "birth" => "1990-01-15"
            ],

            "produto" => [
                "name" => "Product 2",
                "amount" => 2,
                "value" => 2000
            ]

        ];

        return $this->apiEfi->createSubscription($data);

    }
    public function updatePaymentSubscription(){
        dd($_GET);
    }
}
