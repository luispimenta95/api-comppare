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
                foreach ($request->tags as $tagData) {
                    ComparacaoImagemTag::create([
                        'id_comparacao' => $comparacao->id,
                        'id_tag' => $tagData['id_tag'],
                        'valor' => $tagData['valor']
                    ]);
                }
                return response()->json(['message' => 'Comparação atualizada com sucesso.']);
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
                return response()->json(['message' => 'Comparação salva com sucesso.']);
            }
    }

   public function show($id): JsonResponse
{
    // ajuste o nome do model caso o seu seja Photo (singular)
    $photo = Photos::findOrFail($id);

    $comparacoes = ComparacaoImagem::with('tags')
        ->where('id_photo', $id)
        ->get();

    $result = $comparacoes->map(function ($comparacao) use ($photo) {
        $data = $comparacao->toArray();

        $raw = $data['data_comparacao'] ?? null;
        $formatted = null;

        if ($raw) {
            // tenta Y-m-d
            $dt = \DateTime::createFromFormat('Y-m-d', $raw);
            if ($dt) {
                $formatted = $dt->format('d/m/Y');
            } else {
                // tenta d/m/Y
                $dt2 = \DateTime::createFromFormat('d/m/Y', $raw);
                if ($dt2) {
                    $formatted = $dt2->format('d/m/Y');
                } else {
                    // fallback usando strtotime (trim para evitar lixo)
                    $ts = strtotime(trim($raw));
                    if ($ts !== false) {
                        $formatted = date('d/m/Y', $ts);
                    }
                }
            }
        }

        // se não conseguiu formatar, usa a data da foto
        $data['data_comparacao'] = $formatted ?? $photo->created_at->format('d/m/Y');

        // normalize created_at / updated_at (ISO-like). se faltar, usa a data da foto
        try {
            $data['created_at'] = isset($data['created_at'])
                ? (new \DateTime($data['created_at']))->format('Y-m-d\TH:i:s.u\Z')
                : $photo->created_at->format('Y-m-d\TH:i:s.u\Z');
        } catch (\Exception $e) {
            $data['created_at'] = $photo->created_at->format('Y-m-d\TH:i:s.u\Z');
        }

        try {
            $data['updated_at'] = isset($data['updated_at']) && $data['updated_at']
                ? (new \DateTime($data['updated_at']))->format('Y-m-d\TH:i:s.u\Z')
                : ($photo->updated_at ? $photo->updated_at->format('Y-m-d\TH:i:s.u\Z') : null);
        } catch (\Exception $e) {
            $data['updated_at'] = $photo->updated_at ? $photo->updated_at->format('Y-m-d\TH:i:s.u\Z') : null;
        }

        $data['tags'] = $data['tags'] ?? [];

        return $data;
    })->values()->toArray();

    if (empty($result)) {
        $result = [[
            'id' => null,
            'id_usuario' => null,
            'id_photo' => $photo->id,
            'data_comparacao' => $photo->created_at->format('d/m/Y'),
            'created_at' => $photo->created_at->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $photo->updated_at ? $photo->updated_at->format('Y-m-d\TH:i:s.u\Z') : null,
            'tags' => [],
        ]];
    }

    return response()->json($result, 200);
}



}