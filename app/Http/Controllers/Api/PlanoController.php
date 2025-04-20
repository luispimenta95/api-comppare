<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Payments\ApiEfi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Planos;
use App\Http\Util\Helper;
use App\Enums\HttpCodesEnum;

class PlanoController extends Controller
{
    private ApiEfi $apiEfi;

    public function __construct()
    {
        $this->apiEfi = new ApiEfi();
    }

    public function index(): JsonResponse
    {
        $planosAtivos = Planos::where('status', 1)->count();

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'totalPlanos' => Planos::count(),
            'planosAtivos' => $planosAtivos,
            'data' => Planos::where('exibicao', 1)->get()
        ];

        return response()->json($response);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $campos = ['nome', 'descricao', 'valor', 'quantidadeTags', 'quantidadePastas', 'online', 'frequenciaCobranca'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $responseApi = null;
        if ($request->online) {
            $responseApi = json_decode($this->apiEfi->createPlan($request->nome, $request->frequenciaCobranca), true);
        }

        $plano = Planos::create([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'valor' => $request->valor,
            'frequenciaCobranca' => $request->frequenciaCobranca,
            'quantidadeTags' => $request->quantidadeTags,
            'quantidadePastas' => $request->quantidadePastas,
            'idHost' => $request->online ? $responseApi['data']['plan_id'] : null,
        ]);

        $response = isset($plano->id) ?
            [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ] : [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];

        return response()->json($response);
    }

    public function getPlano(int $id): JsonResponse
    {
        $idPlano = trim($id);
        $plano = Planos::find($idPlano);

        $response = isset($plano->id) ?
            [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
                'data' => $plano,
            ] : [
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description(),
            ];

        return response()->json($response);
    }

    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['nome', 'descricao', 'valor', 'quantidadeTags'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
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
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];
        }

        return response()->json($response);
    }

    public function atualizarStatus(Request $request): JsonResponse
    {
        $campos = ['idPlano', 'status'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $plano = Planos::findOrFail($request->idPlano);

        if (isset($plano->id)) {
            $plano->status = $request->status;
            $plano->save();

            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];
        }

        return response()->json($response);
    }

    public function adicionarFuncionalidades(Request $request): JsonResponse
    {
        $campos = ['idPlano', 'funcionalidades'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $plano = Planos::findOrFail($request->idPlano);

        if (isset($plano->id)) {
            $plano->funcionalidades()->sync($request->funcionalidades);

            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];
        }

        return response()->json($response);
    }
}
