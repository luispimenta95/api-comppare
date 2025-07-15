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
     * Lista todas as pastas (não implementado)
     * 
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Cria uma nova pasta para o usuário
     * 
     * Valida se o usuário existe e se não excedeu o limite mensal de pastas do seu plano.
     * Cria a pasta física no storage e registra no banco de dados.
     * 
     * @param Request $request - Deve conter: idUsuario, nomePasta, idPastaPai (opcional)
     * @return JsonResponse - Retorna o ID da pasta criada ou erro
     */
    public function create(Request $request): JsonResponse
    {

        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id', // Validar se o idUsuario existe
            'nomePasta' => 'required|string|max:255', // Validar se o nomePasta é uma string e tem no máximo 255 caracteres
        ]);

        $user = Usuarios::find($request->idUsuario);
        dd($user);

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
     * Armazena um novo recurso (não implementado)
     * 
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Exibe uma pasta específica (não implementado)
     * 
     * @param Pastas $pastas
     * @return void
     */
    public function show(Pastas $pastas)
    {
        //
    }

    /**
     * Exibe formulário para editar uma pasta (não implementado)
     * 
     * @param Pastas $pastas
     * @return void
     */
    public function edit(Pastas $pastas)
    {
        //
    }

    /**
     * Atualiza uma pasta específica (não implementado)
     * 
     * @param Request $request
     * @param Pastas $pastas
     * @return void
     */
    public function update(Request $request, Pastas $pastas)
    {
        //
    }

    /**
     * Remove uma pasta do storage e banco de dados
     * 
     * Valida os dados de entrada, localiza o usuário, deleta a pasta física 
     * do storage e decrementa o contador de pastas criadas pelo usuário.
     * 
     * @param Request $request - Deve conter: idUsuario, nomePasta
     * @return string - Mensagem de resposta da operação
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

    /**
     * Salva imagem(ns) em uma pasta específica
     * 
     * Valida e processa upload de uma ou múltiplas imagens para uma pasta específica.
     * Cria registros na tabela photos para cada imagem carregada com sucesso.
     * Suporta data personalizada para quando a foto foi tirada (formato brasileiro).
     * 
     * Exemplo de request com todos os campos preenchidos:
     * POST /api/pastas/save-image
     * Content-Type: multipart/form-data
     * 
     * Body:
     * - image: [arquivo de imagem] (obrigatório) - pode ser um arquivo único ou array de arquivos
     * - image[]: [arquivo de imagem adicional] (opcional) - para múltiplas imagens
     * - idPasta: 15 (obrigatório) - ID da pasta onde salvar as imagens
     * - dataFoto: "25/12/2024" (opcional) - data em que a foto foi tirada no formato brasileiro
     * 
     * Formatos de imagem aceitos: jpeg, png, jpg, gif, svg
     * Se dataFoto não for fornecida, será usada a data/hora atual
     * Formato de data aceito: d/m/Y (exemplo: 25/12/2024)
     * 
     * @param Request $request - Dados da requisição
     * @return JsonResponse - URLs das imagens carregadas ou erro
     */
    public function saveImageInFolder(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required',
                'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
                'idPasta' => 'required|exists:pastas,id',
                'dataFoto' => 'nullable|string',
            ]);

            $pasta = Pastas::find($request->idPasta);            // Define a data para o campo taken_at - aceita formato brasileiro
            $takenAt = now(); // valor padrão

            if ($request->has('dataFoto') && $request->dataFoto) {
                try {
                    // Converte data no formato brasileiro (d/m/Y) para Carbon
                    $takenAt = \Carbon\Carbon::createFromFormat('d/m/Y', $request->dataFoto);
                } catch (\Exception $e) {
                    // Se falhar, mantém a data atual
                    $takenAt = now();
                }
            }

            // Remove tudo antes de "storage/app/public/"
            $relativePath =  str_replace(
                env('PUBLIC_PATH', '/home/u757410616/domains/comppare.com.br/public_html/api-comppare/storage/app/public/'),

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

    /**
     * Sincroniza tags de uma pasta
     * 
     * Remove todas as tags atuais da pasta e associa as novas tags fornecidas.
     * Operação de substituição completa das tags existentes.
     * 
     * @param Request $request - Deve conter: folder (ID da pasta), tags (array de IDs das tags)
     * @return JsonResponse - Confirmação da operação e lista das tags associadas
     */
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

    /**
     * Remove uma tag específica de uma pasta
     * 
     * Desassocia uma tag específica de uma pasta sem afetar as outras tags.
     * Operação de remoção individual de tag.
     * 
     * @param Request $request - Deve conter: folder_id (ID da pasta), tag_id (ID da tag)
     * @return JsonResponse - Confirmação da remoção da tag
     */
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
    /**
     * Busca todas as pastas de um usuário específico
     * 
     * Valida se o usuário existe e retorna todas as pastas associadas a ele.
     * Útil para listar o conteúdo disponível para um usuário.
     * 
     * @param Request $request - Deve conter: idUsuario (ID do usuário)
     * @return JsonResponse - Lista de pastas do usuário ou erro se usuário não encontrado
     */
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
