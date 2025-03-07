<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Planos;
use App\Http\Util\Helper;
use App\Http\Util\Payments\ApiMercadoPago;
use MercadoPago\MercadoPagoConfig;


class PlanoController extends Controller
{
    private  array $codes;
    private int $gratuidade;
    private $apiMercadoPago;


    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->apiMercadoPago = new ApiMercadoPago();
    }

    public function index(): object
    {
        $planosAtivos = Planos::where('status', 1)->count();
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200],
            'totalPlanos' => Planos::count(),
            'planosAtivos' => $planosAtivos,
            'data' => Planos::all()

        ];
        return response()->json($response);
    }

    public function cadastrarPlano(Request $request): object
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
        MercadoPagoConfig::setAccessToken(env('ACCESS_TOKEN_TST'));

        $nome = $request->nome;
        $valor = $request->valor;

        $response = $this->apiMercadoPago->criarPlano($nome, $valor);
        return response()->json($response);
    }


    public function getPlano(Request $request): object
    {
        $plano = Planos::find($request->idPlano);
        isset($plano->id) ?
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200],
                'data' => $plano
            ] :  $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ];
        return response()->json($response);
    }

    public function atualizarDados(Request $request): object
    {
        $campos = ['nome', 'descricao', 'valor', 'quantidadeTags',];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $plano = Planos::findOrFail($request->idPlano);
        if (isset($plano->id)) {
            $plano->nome = $request->nome;
            $plano->descricao = $request->descricao;
            $plano->valor = $request->valor;
            $plano->quantidadeTags = $request->quantidadeTags;
            $plano->save();
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
        }

        return response()->json($response);
    }

    public function atualizarStatus(Request $request): object
    {
        $campos = ['idPlano', 'status'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $plano = Planos::findOrFail($request->idPlano);
        if (isset($plano->id)) {
            $plano->status = $request->status;
            $plano->save();
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
        }
        return response()->json($response);
    }

    public function adicionarFuncionalidades(Request $request): object
    {
        $campos = ['idPlano', 'funcionalidades'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $plano = Planos::findOrFail($request->idPlano);
        if (isset($plano->id)) {
            $plano->funcionalidades()->sync($request->funcionalidades);
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
        }
        return response()->json($response);
    }
}
