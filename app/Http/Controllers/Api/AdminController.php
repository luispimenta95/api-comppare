<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Util\Payments\ApiMercadoPago;


class AdminController extends Controller
{
    private array $codes = [];
    private $apiMercadoPago = null;


    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->apiMercadoPago = new ApiMercadoPago();

    }

    public function cadastrarPlano(Request $request): JsonResponse
    {
        $campos = ['nome', 'valor'];

        $campos = Helper::validarRequest($request, $campos);
        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $nome = $request->nome;
        $valor = $request->valor;

        $response = $this->apiMercadoPago->criarPlano($nome, $valor);
        return response()->json($response);
    }

}
