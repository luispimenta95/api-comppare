<?php

namespace App\Http\Controllers\Api;

use App\Http\Util\Payments\ApiEfi;
use Carbon\Carbon;
use App\Models\Planos;
use App\Models\Usuarios;
use App\Http\Util\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Util\MailHelper;
use Illuminate\Support\Facades\Log;

// Inicializar chave do Mercado Pago

use App\Http\Util\Payments\ApiMercadoPago;
use Illuminate\Http\Request;

class VendasController extends Controller
{
    //update server
    private array $codes = [];
    private ApiEfi $apiEfi;


    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->apiEfi = new ApiEfi();

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
    public function updatePayment(Request $request)
    {
        Log::info('Dados do Request:', $request->all());

    }
}
