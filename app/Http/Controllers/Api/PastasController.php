<?php

namespace App\Http\Controllers\Api;
use App\Enums\HttpCodesEnum;
use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $campos = ['nomePasta', 'idUsuario'];

        // Validação dos campos obrigatórios
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

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
        $monthLimit = Planos::find($idPlano)->limitePastas;

        // Contagem de pastas e subpastas criadas pelo usuário no mês atual
        $pastasCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->whereYear('created_at', $currentYear)  // Filtra pelo ano atual
            ->whereMonth('created_at', $currentMonth)  // Filtra pelo mês atual
            ->count();

        // Contagem de subpastas associadas a este usuário
        $subpastasCriadasNoMes = Pastas::whereHas('subpastas', function ($query) use ($user, $currentYear, $currentMonth) {
            $query->where('idUsuario', $user->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth);
        })->count();

        $totalFolders = $pastasCriadasNoMes + $subpastasCriadasNoMes;

        // Verifica se o número de pastas (incluindo subpastas) criadas é menor que o limite do plano
        if ($totalFolders < $monthLimit) {
            // Prossegue com a criação da pasta ou subpasta
            $folderName = $user->id . '/' . $request->nomePasta;
            $folder = json_decode(Helper::createFolder($folderName));

            if ($folder->path !== null) {
                // Criação da pasta principal
                $novaPasta = Pastas::create([
                    'nome' => $folderName,
                    'idUsuario' => $user->id,
                    'caminho' => $folder->path
                ]);

                // Se a pasta for criada com sucesso, associamos o usuário à pasta
                $novaPasta->usuarios()->attach($user->id);

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
        $campos = ['nomePasta', 'idUsuario'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $user = Usuarios::find($request->idUsuario);
        $folderName = 'public/' . $user->id . '/' . $request->nomePasta;
        $response = json_decode(Helper::deleteFolder($folderName));
        return $response->message;     //
    }

    public function saveImageInFolder(Request $request)
    {
        // Validação para garantir que o idPasta e a imagem estão presentes
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validação da imagem
            'idPasta' => 'required|exists:pastas,id', // Validar se o idPasta existe
        ]);

        // Recuperar a pasta com base no idPasta
        $pasta = Pastas::find($request->idPasta);

        // Verificar se a pasta foi encontrada
        if (!$pasta || !Storage::exists($pasta->path)) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Pasta não encontrada.',
            ]);
        }

        // Verificar se existe o arquivo de imagem no request
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Gerar um nome único para a imagem
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            // Armazenar a imagem na pasta recuperada (path)
            $path = $image->storeAs($pasta->path, $imageName, 'public'); // 'public' indica o disco público

            // Retornar o caminho da imagem armazenada
            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Imagem carregada com sucesso!',
                'image_path' => Storage::url($path),  // Retorna o caminho público da imagem
            ]);
        }

        // Caso o arquivo não tenha sido enviado
        return response()->json([
            'codRetorno' => HttpCodesEnum::BadRequest->value,
            'message' => 'Nenhuma imagem foi enviada.',
        ]);
    }
    //Rodar commando abaixo:
//php artisan storage:link


}
