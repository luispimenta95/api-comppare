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
use Illuminate\Http\Request;

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

    public function createSubscription(Request $request): JsonResponse
    {
        $campos = ['usuario', 'plano', 'token'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $usuario = Usuarios::find($request->usuario);
        $plano = Planos::find($request->plano);

        $data = [
            "cardToken" => $request->token,
            "idPlano" =>$plano->idHost,
            "usuario" => [
                "name" => $usuario->nome,
                "cpf" => $usuario->cpf,
                "phone_number" => $usuario->telefone,
                "email" => $usuario->email,
                "birth" => Carbon::parse($usuario->dataNascimento)->format('Y-m-d')
            ],

            "produto" => [
                "name" => $plano->nome,
                "amount" => Helper::QUANTIDADE,
                "value" => $plano->valor * 100 // Valor = Valor plano * 100
            ]

        ];

        $responseApi = json_decode($this->apiEfi->createSubscription($data), true);

        if($responseApi['code'] == 200){
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
            $dadosEmail = [
                'nome' => $usuario->nome,
            ];

            MailHelper::confirmacaoAssinatura($dadosEmail, $usuario->email);
        }else{
            $response = [
                'codRetorno' => 400,
                'message' => $responseApi['description']
            ];
        }
            return response()->json($response);
    }
}
