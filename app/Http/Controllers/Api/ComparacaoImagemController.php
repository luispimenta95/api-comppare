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
            'tags' => 'nullable|array',
            'tags.*.id_tag' => 'required|exists:tags,id',
            'tags.*.id_tag' => 'required_with:tags|exists:tags,id',
            'tags.*.valor' => 'required_with:tags|string'
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
            return response()->json(['message' => 'Foto não encontrada ou sem pasta associada.'], 404);
        }
        $pasta = Pastas::find($photo->pasta_id);
        if (!$pasta || $pasta->idUsuario != $request->id_usuario) {
            return response()->json(['message' => 'A foto não pertence ao usuário informado.'], 403);
        }

        // Validar se todas as tags são globais ou do usuário
        $tagsArray = is_array($request->tags) ? $request->tags : [];
        if (!empty($tagsArray)) {
            $tagIds = array_map(function ($tag) {
                return $tag['id_tag'];
            }, $tagsArray);
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
        }

        // Converter data_comparacao do formato brasileiro para Y-m-d
        $dataComparacao = \DateTime::createFromFormat('d/m/Y', $request->data_comparacao);
        if (!$dataComparacao) {
            return response()->json([
                'message' => 'Data de comparação inválida. Use o formato dd/mm/yyyy.'
            ], 422);
        }

            // Verifica se já existe comparação para a foto
            $comparacao = ComparacaoImagem::where('id_photo', $request->id_photo)->first();
            if ($comparacao) {
                // Atualiza os dados da comparação existente, sempre sobrescrevendo a data
                $comparacao->id_usuario = $request->id_usuario;
                $comparacao->data_comparacao = $dataComparacao->format('Y-m-d'); // Sempre usa a data enviada
                $comparacao->save();

                // Remove as tags antigas
                ComparacaoImagemTag::where('id_comparacao', $comparacao->id)->delete();

                // Adiciona as novas tags
                if (isset($request->tags)) {
                    foreach ($request->tags as $tagData) {
                        ComparacaoImagemTag::create([
                            'id_comparacao' => $comparacao->id,
                            'id_tag' => $tagData['id_tag'],
                            'valor' => $tagData['valor']
                        ]);
                    }
                }
               
                return response()->json(['message' => 'Comparação atualizada com sucesso.']);
            } else {
                // Cria nova comparação
                $comparacao = ComparacaoImagem::create([
                    'id_usuario' => $request->id_usuario,
                    'id_photo' => $request->id_photo,
                    'data_comparacao' => $dataComparacao->format('Y-m-d')
                ]);

            if (isset($request->tags)) {
                foreach ($request->tags as $tagData) {
                    ComparacaoImagemTag::create([
                        'id_comparacao' => $comparacao->id,
                        'id_tag' => $tagData['id_tag'],
                        'valor' => $tagData['valor']
                    ]);
                }
            }
                return response()->json(['message' => 'Comparação salva com sucesso.']);
            }
    }

    public function show($id): array | JsonResponse
    {
        $comparacaoArray = [];
        $comparacoes = ComparacaoImagem::with('tags')
            ->where('id_photo', $id)
            ->get();

        if ($comparacoes->isEmpty()) {
            $photo = Photos::find($id);
            $dataComparacao = null;
            if ($photo && $photo->created_at) {
                $dataObj = \DateTime::createFromFormat('Y-m-d H:i:s', $photo->created_at);
                $dataComparacao = $dataObj ? $dataObj->format('d/m/Y') : null;
                $comparacaoArray = [
                    'data_comparacao' => $dataComparacao
                ];
                            return $comparacaoArray;

            }
      
        }

        // Ajusta o campo data_comparacao para o padrão brasileiro
        $comparacoesFormatadas = $comparacoes->map(function ($comparacao) {
            $comparacaoArray = $comparacao->toArray();
            if (!empty($comparacaoArray['data_comparacao'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $comparacaoArray['data_comparacao']);
                if ($date) {
                    $comparacaoArray['data_comparacao'] = $date->format('d/m/Y');
                }
            }
            return $comparacaoArray;
        });

        return response()->json($comparacoesFormatadas);
    }
}