<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComparacaoImagem;
use App\Models\ComparacaoImagemTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
