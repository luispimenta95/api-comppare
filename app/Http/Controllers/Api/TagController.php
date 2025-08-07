<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Tag;
use App\Models\Usuarios;
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
     * 
     * Verifica se o usuário ainda pode criar tags baseado no limite do plano.
     * 
     * Exemplo de request:
     * {
     *   "nomeTag": "Nome da Tag",
     *   "usuario": 1
     * }
     */
    public function cadastrarTag(Request $request): JsonResponse
    {
        $request->validate([
            'nomeTag' => 'required|string|max:255',
            'usuario' => 'required|integer|exists:usuarios,id'
        ]);

        // Buscar o usuário e seu plano
        $usuario = Usuarios::with('plano')->find($request->usuario);
        
        if (!$usuario) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Usuário não encontrado.',
            ], HttpCodesEnum::NotFound->value);
        }

        if (!$usuario->idPlano) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => 'Usuário não possui plano associado.',
            ], HttpCodesEnum::BadRequest->value);
        }

        // Contar tags pessoais já criadas pelo usuário
        $tagsPersonaisCriadas = Tag::where('idUsuarioCriador', $request->usuario)
            ->where('status', Helper::ATIVO)
            ->count();

        // Verificar se o limite do plano foi atingido
        $limiteTags = $usuario->plano->quantidadeTags;
        
        if ($tagsPersonaisCriadas >= $limiteTags) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Forbidden->value,
                'message' => 'Limite de tags do plano atingido.',
                'detalhes' => [
                    'limite_plano' => $limiteTags,
                    'tags_criadas' => $tagsPersonaisCriadas,
                    'plano_atual' => $usuario->plano->nome,
                    'sugestao' => 'Faça upgrade do seu plano para criar mais tags.'
                ]
            ], HttpCodesEnum::Forbidden->value);
        }

        // Verificar se já existe uma tag com o mesmo nome para este usuário
        $tagExistente = Tag::where('idUsuarioCriador', $request->usuario)
            ->where('nomeTag', $request->nomeTag)
            ->where('status', Helper::ATIVO)
            ->first();

        if ($tagExistente) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Conflict->value,
                'message' => 'Você já possui uma tag com este nome.',
                'tag_existente' => [
                    'id' => $tagExistente->id,
                    'nome' => $tagExistente->nomeTag,
                    'criada_em' => $tagExistente->created_at->format('Y-m-d H:i:s')
                ]
            ], HttpCodesEnum::Conflict->value);
        }

        // Criar a nova tag
        $novaTag = Tag::create([
            'nomeTag' => $request->nomeTag,
            'idUsuarioCriador' => $request->usuario,
            'status' => Helper::ATIVO
        ]);

        $response = [
            'codRetorno' => HttpCodesEnum::Created->value,
            'message' => 'Tag criada com sucesso.',
            'tag' => [
                'id' => $novaTag->id,
                'nome' => $novaTag->nomeTag,
                'tipo' => 'pessoal',
                'criada_em' => $novaTag->created_at->format('Y-m-d H:i:s')
            ],
            'limites' => [
                'usado' => $tagsPersonaisCriadas + 1,
                'limite' => $limiteTags,
                'restante' => $limiteTags - ($tagsPersonaisCriadas + 1)
            ]
        ];

        return response()->json($response, HttpCodesEnum::Created->value);
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
