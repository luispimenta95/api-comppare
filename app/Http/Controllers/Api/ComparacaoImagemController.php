<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComparacaoImagem;
use App\Models\ComparacaoImagemTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Photos;
use App\Models\Pastas;
use App\Models\Tag;
use App\Http\Util\Helper;

class ComparacaoImagemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_usuario' => 'required|exists:usuarios,id',
            'id_photo' => 'required|exists:photos,id',
            'data_comparacao' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'tags' => 'required|array',
            'tags.*.id_tag' => 'required|exists:tags,id',
            'tags.*.valor' => 'required|string'
        ]);

        // Verificar se a foto pertence ao usuário informado
        $photo = Photos::find($request->id_photo);
        if (!$photo || $photo->pasta_id === null) {
            return response()->json(['message' => 'Foto não encontrada ou sem pasta associada.'], 404);
        }
        $pasta = Pastas::find($photo->pasta_id);
        if (!$pasta || $pasta->idUsuario != $request->id_usuario) {
            return response()->json(['message' => 'A foto não pertence ao usuário informado.'], 403);
        }

        // Validar se todas as tags são globais ou do usuário
        $tagIds = array_map(function ($tag) {
            return $tag['id_tag'];
        }, $request->tags);
        $tagsValidas = Tag::whereIn('id', $tagIds)
            ->where(function ($query) use ($request) {
                $query->where('idUsuarioCriador', $request->id_usuario)
                    ->orWhere(function ($q) {
                        $q->whereHas('usuario', function ($u) {
                            $u->where('idPerfil', Helper::ID_PERFIL_ADMIN);
                        });
                    });
            })
            ->pluck('id')
            ->toArray();

        $tagsInvalidas = array_diff($tagIds, $tagsValidas);
        if (count($tagsInvalidas) > 0) {
            return response()->json([
                'message' => 'Uma ou mais tags não são globais nem pertencem ao usuário.',
                'tags_invalidas' => $tagsInvalidas
            ], 403);
        }

        // Converter data_comparacao do formato brasileiro para Y-m-d
        $dataComparacao = \DateTime::createFromFormat('d/m/Y', $request->data_comparacao);
        if (!$dataComparacao) {
            return response()->json([
                'message' => 'Data de comparação inválida. Use o formato dd/mm/yyyy.'
            ], 422);
        }

        $comparacao = ComparacaoImagem::create([
            'id_usuario' => $request->id_usuario,
            'id_photo' => $request->id_photo,
            'data_comparacao' => $dataComparacao->format('Y-m-d')
        ]);

        foreach ($request->tags as $tagData) {
            ComparacaoImagemTag::create([
                'id_comparacao' => $comparacao->id,
                'id_tag' => $tagData['id_tag'],
                'valor' => $tagData['valor']
            ]);
        }

        return response()->json(['message' => 'Comparação salva com sucesso.']);
    }

    public function show($id): JsonResponse
    {
        $comparacoes = ComparacaoImagem::with('tags')
            ->where('id_photo', $id)
            ->get();

        if ($comparacoes->isEmpty()) {
            return response()->json(['message' => 'Nenhuma comparação encontrada para esta foto.'], 404);
        }

        return response()->json($comparacoes);
    }
}
