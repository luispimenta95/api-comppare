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
use Illuminate\Support\Facades\Log;


class ComparacaoImagemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_usuario' => 'required|exists:usuarios,id',
            'id_photo' => 'required|exists:photos,id',
            'data_comparacao' => ['required', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'tags' => 'sometimes|array',
            'tags.*.id_tag' => 'required|exists:tags,id',
            'tags.*.valor' => 'required|string'
        ]);
        Log::info('Store ComparacaoImagem called', [
            'id_usuario' => $request->id_usuario,
            'id_photo' => $request->id_photo,
            'data_comparacao' => $request->data_comparacao,
            'tags' => $request->tags
        ]);

        // Verificar se a foto pertence ao usuário informado
        $photo = Photos::find($request->id_photo);
        Log::info('Verificando foto', [
            'photo' => $photo
        ]);
        if (!$photo || $photo->pasta_id === null) {
            return response()->json(['message' => 'Foto não encontrada ou sem pasta associada.',
            "codRetorno" => 404,
        ], 404);
        }
        $pasta = Pastas::find($photo->pasta_id);
        if (!$pasta || $pasta->idUsuario != $request->id_usuario) {
            return response()->json(['message' => 'A foto não pertence ao usuário informado.',
            "codRetorno" => 403,
        ], 403);
        }

        // Validar se todas as tags são globais ou do usuário
        $tagsRequest = $request->tags ?? [];
        $tagIds = array_map(function ($tag) {
            return $tag['id_tag'];
        }, $tagsRequest);
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
                'codRetorno' => 403,
                'tags_invalidas' => $tagsInvalidas
            ], 403);
        }

        // Converter data_comparacao do formato brasileiro para Y-m-d
        $dataComparacao = \DateTime::createFromFormat('d/m/Y', $request->data_comparacao);
        if (!$dataComparacao) {
            return response()->json([
                'message' => 'Data de comparação inválida. Use o formato dd/mm/yyyy.',
                'codRetorno' => 422,
            ], 422);
        }

            // Verifica se já existe comparação para a foto
            $comparacao = ComparacaoImagem::where('id_photo', $request->id_photo)->first();
            if ($comparacao) {
                // Atualiza os dados da comparação existente, sempre sobrescrevendo a data
                $comparacao->id_usuario = $request->id_usuario;
                $comparacao->data_comparacao = $dataComparacao->format('Y-m-d'); // Sempre usa a data enviada
                $comparacao->save();

                Log::info('Atualizando foto', [
                    'photo' => $photo
                ]);
                $photo->taken_at = $dataComparacao->format('Y-m-d');
                $photo->save();

                Log::info('Foto atualizada', [
                    'photo' => $photo
                ]);

                // Só remove e recria as tags se o parâmetro 'tags' for enviado
                if (isset($request->tags)) {
                    ComparacaoImagemTag::where('id_comparacao', $comparacao->id)->delete();
                    foreach ($request->tags as $tagData) {
                        ComparacaoImagemTag::create([
                            'id_comparacao' => $comparacao->id,
                            'id_tag' => $tagData['id_tag'],
                            'valor' => $tagData['valor']
                        ]);
                    }
                }
                return response()->json(['message' => 'Comparação atualizada com sucesso.',
                    'codRetorno' => 200
                ]);
            } else {
                // Cria nova comparação
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
                return response()->json(['message' => 'Comparação salva com sucesso.',
                    'codRetorno' => 200
                ], 200);
            }
    }

    public function show(int $id): JsonResponse
{
    $photo = Photos::findOrFail($id);

    $comparacoes = ComparacaoImagem::with('tags')
        ->where('id_photo', $id)
        ->get();

    // Ajusta o campo data_comparacao para o padrão brasileiro e adiciona nomeTag nas tags
    $comparacoesFormatadas = $comparacoes->map(function ($comparacao) {
        $comparacaoArray = $comparacao->toArray();
        if (!empty($comparacaoArray['data_comparacao'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $comparacaoArray['data_comparacao']);
            if ($date) {
                $comparacaoArray['data_comparacao'] = $date->format('d/m/Y');
            }
        }
        // Adiciona nomeTag em cada tag
        if (!empty($comparacaoArray['tags'])) {
            dd($comparacaoArray['tags']);
            $comparacaoArray['tags'] = array_map(function ($tag) {
                $tagModel = Tag::find($tag['id']);
                $tag['nome'] = $tagModel ? $tagModel->nomeTag : null;
                return $tag;
            }, $comparacaoArray['tags']);
        }
        return $comparacaoArray;
    });

    // Se não houver comparações, devolve um array com objeto padrão
    if ($comparacoesFormatadas->isEmpty()) {
        $comparacoesFormatadas = collect([[
            'id'              => 0,
            'id_usuario'      => 0,
            'id_photo'        => $photo->id,
            'data_comparacao' => $photo->created_at->format('d/m/Y'),
            'created_at'      => now()->format('Y-m-d H:i:s'),
            'updated_at'      => now()->format('Y-m-d H:i:s'),
            'tags'            => [],
        ]]);
    }

    return response()->json($comparacoesFormatadas, 200);
}


}