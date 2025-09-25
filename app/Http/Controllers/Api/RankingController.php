<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Ponto;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    private array $codes = [];

    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
    }
    public function index(): JsonResponse
    {
        $pontos = Ponto::selectRaw('idUsuario, SUM(pontos) as total')
            ->groupBy('idUsuario')
            ->orderByDesc('total')
            ->with('usuario')
            ->get();

        $resultado = $pontos->map(function ($item) {
            return [
                'nome' => $item->usuario->apelido != null ? $item->usuario->apelido :  $item->usuario->primeiroNome,
                'pontos' => $item->total
            ];
        });

        return response()->json($resultado);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function updatePoints(Request $request): JsonResponse
    {
        $campos = ['pontos', 'usuario','acao'];

        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }
        $user = Usuarios::find($request->usuario);
        if(!$user){
            $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404],
            ];

            return response()->json($response);
        }

        Ponto::create([
            'idUsuario' => $request->usuario,
            'pontos' => strtolower($request->acao) === 'adicionar' ? $request->pontos : -$request->pontos
        ]);

        $user->pontos = strtolower($request->acao) === 'adicionar' ? $user->pontos + $request->pontos : $user->pontos - $request->pontos;
        $user->save();
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
}
