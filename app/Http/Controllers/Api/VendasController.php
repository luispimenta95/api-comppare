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
            "idPlano" => $plano->idHost,
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
            dd($responseApi);
        if ($responseApi['code'] == 200) {
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
            $dadosEmail = [
                'nome' => $usuario->nome,
            ];

            MailHelper::confirmacaoAssinatura($dadosEmail, $usuario->email);
        } else {
            $response = [
                'codRetorno' => 400,
                'message' => $responseApi['description']
            ];
        }
        return response()->json($response);
    }

    public function updatePayment(Request $request)
    {


        $chargeNotification = $this->apiEfi->getSubscriptionDetail($request->notification);
        $data = json_decode($chargeNotification, true);
        /*
         [2025-03-17 12:37:13] local.INFO: Dados do Pagamento: ["{\"code\":200,\"data\":[{\"id\":1,\"type\":\"subscription\",\"custom_id\":null,\"status\":{\"current\":\"new\",\"previous\":null},\"identifiers\":{\"subscription_id\":95609},\"created_at\":\"2025-03-17 12:37:06\"},{\"id\":2,\"type\":\"subscription\",\"custom_id\":null,\"status\":{\"current\":\"new_charge\",\"previous\":\"new\"},\"identifiers\":{\"subscription_id\":95609},\"created_at\":\"2025-03-17 12:37:06\"},{\"id\":3,\"type\":\"subscription\",\"custom_id\":null,\"status\":{\"current\":\"active\",\"previous\":\"new_charge\"},\"identifiers\":{\"subscription_id\":95609},\"created_at\":\"2025-03-17 12:37:06\"},{\"id\":4,\"type\":\"subscription_charge\",\"custom_id\":null,\"status\":{\"current\":\"new\",\"previous\":null},\"identifiers\":{\"subscription_id\":95609,\"charge_id\":44514485},\"created_at\":\"2025-03-17 12:37:06\"},{\"id\":5,\"type\":\"subscription_charge\",\"custom_id\":null,\"status\":{\"current\":\"waiting\",\"previous\":\"new\"},\"identifiers\":{\"subscription_id\":95609,\"charge_id\":44514485},\"created_at\":\"2025-03-17 12:37:06\"}]}"]

         */

// Iterar sobre os dados e verificar o status "paid"
        foreach ($data['data'] as $item) {
            // Verificar se o tipo é 'subscription_charge' e o status 'current' é 'paid'
            if ($item['type'] === 'subscription_charge' && $item['status']['current'] === 'paid') {

            }
        }
    }
}
