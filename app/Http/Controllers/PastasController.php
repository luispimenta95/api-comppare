<?php

namespace App\Http\Controllers;

use App\Http\Util\Helper;
use App\Models\Pastas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuarios;

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
        $folderName = self::ROOT_PATH . $user->id . '/' . $request->nomePasta;
        $folder = json_decode(Helper::createFolder($folderName));
        dd($folder);
        if ($folder->path !== null) {
            Pastas::create([
                'nome' => $folderName,
                'idUsuario' => $user->id,
                'caminho' => $folder->path
            ]);
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
