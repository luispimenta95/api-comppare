<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Http\Util\Payments\ApiMercadoPago;
use App\Models\Planos;
use Illuminate\Http\Request;


class AdminController extends Controller
{
    private  $apiMercadoPago;
    private array $codes = [];

    public function __construct()
    {
        $this->apiMercadoPago = new ApiMercadoPago();
        $this->codes = Helper::getHttpCodes();

    }
    function criarPlanoAssinatura(Request $request)
    {
        $campos = ['nome', 'descricao', 'valor', 'quantidadeTags'];

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
        $responseApi = $this->apiMercadoPago->criarPlanoAssinatura($nome, $valor);
dd($responseApi->id);
        $plano = Planos::create([
            'nome' => $nome,
            'descricao' => $request->descricao,
            'valor' => $valor,
            'quantidadeTags' => $request->quantidadeTags,
            'idMercadoPago' => $responseApi->id,
            'linkAssinatura' => $responseApi->init_point,
        ]);
        isset($plano->id) ?
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ] :  $response = [
            'codRetorno' => 500,
            'message' => $this->codes[500]
        ];
        return response()->json($response);
    }

}
