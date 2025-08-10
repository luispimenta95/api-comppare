<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pastas;
use App\Models\Tag;
use Illuminate\Http\Request;
use App\Models\Photos;
class PhotoController extends Controller
{

    public function __construct()
    {
        // Não é mais necessário armazenar códigos, pois agora usamos a HttpCodesEnum diretamente.
    }

    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // Máximo 5MB
            'folder_id' => 'required|exists:folders,id', // Valida que existe no banco
        ]);

        // Busca a folder no banco
        $folder = Pastas::findOrFail($request->folder_id);

        // Usa o ID ou outro campo para montar a pasta

        // Cria o registro da foto
        $photo = Photos::create([
            'path' => $folder->path,
            'folder_id' => $folder->id, // (opcional, mas recomendado!)
        ]);

        return response()->json([
            'message' => 'Foto salva com sucesso!',
            'photo_id' => $photo->id,
            'path' => $folder->path,
        ]);
    }

    public function attachTags(Request $request)
    {
        $request->validate([
            'photo_id' => 'required|exists:photos,id',
            'tags' => 'required|array',
            'tags.*.label' => 'required|string|max:255',
            'tags.*.value' => 'nullable|string|max:255',
        ]);

        // Busca a foto pelo ID
        $photo = Photos::findOrFail($request->photo_id);

        $attachData = [];

        foreach ($request->tags as $tagData) {
            // Procura ou cria a tag pelo label
            $tag = Tag::firstOrCreate(
                ['label' => $tagData['label']],
                [
                    'valor' => $tagData['value'] ?? null,
                ]
            );

            // Prepara o array para attach
            $attachData[$tag->id] = [
                'value' => $tagData['value'] ?? null,
            ];
        }

        // Faz o attach
        $photo->tags()->attach($attachData);

        return response()->json([
            'message' => 'Tags associadas com sucesso!',
            'photo_id' => $photo->id,
        ]);
    }
}
