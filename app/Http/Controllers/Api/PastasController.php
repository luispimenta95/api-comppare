<?php

namespace App\Http\Controllers\Api;

use App\Enums\HttpCodesEnum;
use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Pastas;
use App\Models\Photos;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PastasController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): JsonResponse
    {

        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id', // Validar se o idUsuario existe
            'nomePasta' => 'required|string|max:255', // Validar se o nomePasta é uma string e tem no máximo 255 caracteres
        ]);

        $user = Usuarios::find($request->idUsuario);

        // Verifica se o usuário foi encontrado
        if (!$user) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::UserNotFound->description(),
            ]);
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;
        $idPlano = $user->idPlano;

        $monthLimit = Planos::find($idPlano)->quantidadePastas;


        // Contagem de pastas e subpastas criadas pelo usuário no mês atual
        $pastasCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->whereYear('created_at', $currentYear)  // Filtra pelo ano atual
            ->whereMonth('created_at', $currentMonth)  // Filtra pelo mês atual
            ->count();
        //dd($pastasCriadasNoMes);

        $totalFolders = $pastasCriadasNoMes;


        // Verifica se o número de pastas (incluindo subpastas) criadas é menor que o limite do plano
        if ($totalFolders < $monthLimit) {
            // Prossegue com a criação da pasta ou subpasta
            $folderName =  $user->primeiroNome . '_' . $user->sobrenome . '/' . $request->nomePasta;
            $folder = Helper::createFolder($folderName);

            if ($folder['path'] !== null) {
                // Criação da pasta principal
                $novaPasta = Pastas::create([
                    'nome' => $folderName,
                    'idUsuario' => $user->id,
                    'caminho' => $folder['path']
                ]);


                // Se a pasta for criada com sucesso, associamos o usuário à pasta
                $novaPasta->usuario()->attach($user->id);

                // Se a pasta for uma subpasta, associamos à pasta pai
                if ($request->idPastaPai) {
                    $pastaPai = Pastas::find($request->idPastaPai);
                    if ($pastaPai) {
                        $novaPasta->pastaPai()->associate($pastaPai);
                        $novaPasta->save();
                    }
                }

                // Se o convite foi bem sucedido, atualiza o número de pastas criadas
                $user->increment('pastasCriadas');
                // Retorna a resposta de sucesso
                $response = [
                    'idPasta' => $novaPasta->id,
                    'codRetorno' => HttpCodesEnum::OK->value,
                    'message' => HttpCodesEnum::OK->description()
                ];
                return response()->json($response);
            } else {
                $response = [
                    'codRetorno' => HttpCodesEnum::InternalServerError->value,
                    'message' => HttpCodesEnum::InternalServerError->description()
                ];
                return response()->json($response);
            }
        } else {
            // Caso o limite de pastas ou subpastas tenha sido atingido
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::MonthlyFolderLimitReached->description()
            ];
            return response()->json($response);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Pastas $pastas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pastas $pastas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pastas $pastas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id', // Validar se o idUsuario existe
            'nomePasta' => 'required|string|max:255', // Validar se o nomePasta é uma string e tem no máximo 255 caracteres
        ]);
        $user = Usuarios::find($request->idUsuario);
        $folderName = 'public/' . $user->id . '/' . $request->nomePasta;
        $response = json_decode(Helper::deleteFolder($folderName));
        $user->decrement('pastasCriadas');
        return $response->message;
    }

    public function saveImageInFolder(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required',
                'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
                'idPasta' => 'required|exists:pastas,id',
            ]);

            $pasta = Pastas::find($request->idPasta);

            // Remove tudo antes de "storage/app/public/"
            $relativePath = str_replace(
                config('app.publicPath'),
                '',
                $pasta->caminho
            );
            $relativePath = trim($relativePath, '/');

            $uploadedImages = [];

            $images = is_array($request->file('image'))
                ? $request->file('image')
                : [$request->file('image')];

            foreach ($images as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs($relativePath, $imageName, 'public');
                    $imageUrl = Storage::url($path);
                    
                    // Salva a imagem na tabela photos
                    Photos::create([
                        'pasta_id' => $pasta->id,
                        'path' => $imageUrl,
                        'taken_at' => now()
                    ]);
                    
                    $uploadedImages[] = $imageUrl;
                }
            }

            if (empty($uploadedImages)) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'Nenhuma imagem válida foi enviada.',
                ]);
            }

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Imagem(ns) carregada(s) com sucesso!',
                'image_paths' => $uploadedImages,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => 'Validação falhou. Dados fornecidos inválidos.',
            ]);
        }
    }

    public function syncTagsToFolder(Request $request)
    {
        $request->validate([
            'folder' => 'required|exists:folders,id',
            'tags' => 'required|array',
            'tags.*' => 'required|integer|exists:tags,id',
        ]);

        $folder = Pastas::findOrFail($request->folder);

        // Atualiza as tags da pasta, removendo as antigas
        $folder->tags()->sync($request->tags);

        return response()->json([
            'message' => 'Tags associadas com sucesso!',
            'folder_id' => $folder->id,
            'tags' => $request->tags,
        ]);
    }

    public function detachTagFromFolder(Request $request)
    {
        $request->validate([
            'folder_id' => 'required|exists:folders,id',
            'tag_id' => 'required|exists:tags,id',
        ]);

        $folder = Pastas::findOrFail($request->folder_id);

        // Remove a tag da pasta
        $folder->tags()->detach($request->tag_id);

        return response()->json([
            'message' => 'Tag removida da pasta com sucesso!',
            'folder_id' => $folder->id,
            'tag_id' => $request->tag_id,
        ]);
    }
    public function getFoldersByUser(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id', // Validar se o idUsuario existe
        ]);

        $user = Usuarios::find($request->idUsuario);

        // Verifica se o usuário foi encontrado
        if (!$user) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::UserNotFound->description(),
            ]);
        }

        // Busca as pastas do usuário
        $pastas = Pastas::where('idUsuario', $user->id)->get();

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'pastas' => $pastas,
        ]);
    }
   


}
