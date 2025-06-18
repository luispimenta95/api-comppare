<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\HttpCodesEnum;

class TagController extends Controller
{

    public function __construct()
    {
        // Não é mais necessário armazenar códigos, pois agora usamos a HttpCodesEnum diretamente.
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tagsAtivas = Tag::where('status', 1)->count();

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'totalTags' => Tag::count(),
            'tagsAtivas' => $tagsAtivas,
            'data' => Tag::all()
        ];

        return response()->json($response);
    }

    /**
     * Cadastrar uma nova tag.
     */
    public function cadastrarTag(Request $request): JsonResponse
    {
        $request->validate([
            'valor' => 'required|string|max:255',
            'label' => 'required|string|max:255',
        ]);

        Tag::create([
            'label' => $request->label,
            'valor' => $request->valor,
            'idUsuarioCriador' => $request->usuario,
        ]);

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
        ];

        return response()->json($response);
    }

    /**
     * Atualizar o status de uma tag.
     */
    public function atualizarStatus(Request $request): JsonResponse
    {
        $campos = ['idTag'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $tag = Tag::findOrFail($request->idTag);

        if (isset($tag->id)) {
            $tag->status = $request->status;
            $tag->save();

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

    /**
     * Atualizar os dados de uma tag.
     */
    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['nome', 'descricao', 'idTag'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $tag = Tag::findOrFail($request->idTag);

        if (isset($tag->id)) {
            $tag->nome = $request->nome;
            $tag->descricao = $request->descricao;
            $tag->save();

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

    /**
     * Buscar tags por usuário.
     */
    public function getTagsByUsuario(Request $request): JsonResponse
    {
        $campos = ['usuario'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $tags = Tag::where(function ($query) use ($request) {
            $query->where('idUsuarioCriador', $request->usuario)
                ->where('status', Helper::ATIVO);
        })->orWhere(function ($query) {
            $query->whereHas('usuario', function ($q) {
                $q->where('idPerfil', Helper::ID_PERFIL_ADMIN);
            })->where('status', Helper::ATIVO);
        })->get();

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'totalTags' => count($tags),
            'data' => $tags,
        ];

        return response()->json($response);
    }

    /**
     * Recuperar uma tag específica.
     */
    public function getTag(Request $request): JsonResponse
    {
        $campos = ['idTag'];
        $campos = Helper::validarRequest($request, $campos);
        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }
        $tag = Tag::find($request->idTag);
        if ($tag) {
            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
                'data' => $tag,
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description(),
            ];
}
        return response()->json($response);
    }
}
