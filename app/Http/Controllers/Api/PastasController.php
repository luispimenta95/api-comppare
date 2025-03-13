<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PastasController extends Controller
{
    private array $codes = [];
    const ROOT_PATH = 'usuarios/';
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

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $user = Usuarios::find($request->idUsuario);

// Verifica se o usuário possui o atributo 'pastasCriadas' e calcula as pastas criadas no mês atual
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $idPlano = $user->idPlano;
        $limitePastas = Planos::find($idPlano)->limitePastas;

        $pastasCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->whereYear('created_at', $currentYear)  // Filtra pelo ano atual
            ->whereMonth('created_at', $currentMonth)  // Filtra pelo mês atual
            ->count();

// Verifica se o número de pastas criadas é menor que 3
        if ($pastasCriadasNoMes < $limitePastas) {
            // Prossegue com a criação da pasta
            $folderName = self::ROOT_PATH . $user->id . '/' . $request->nomePasta;
            $folder = json_decode(Helper::createFolder($folderName));

            if ($folder->path !== null) {
                Pastas::create([
                    'nome' => $folderName,
                    'idUsuario' => $user->id,
                    'caminho' => $folder->path
                ]);
            }
        } else {
            // Retorna uma mensagem ou erro indicando que o limite de pastas foi atingido
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[-11]
            ];
            return response()->json($response);

        }
        if ($folder->path === null) {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
            return response()->json($response);
        }
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200]

        ];
        return response()->json($response);
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
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $user = Usuarios::find($request->idUsuario);
        $folderName = self::ROOT_PATH . $user->id . '/' . $request->nomePasta;
        $response = json_decode(Helper::deleteFolder($folderName));
        return $response->message;     //
    }
}
