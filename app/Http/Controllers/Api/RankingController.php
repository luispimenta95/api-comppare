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
    protected $pointRules = [
        'post_created' => 10,
        'comment_added' => 5,
        'like_received' => 2,
    ];

    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
    }
    public function index()
    {
        return Ponto::selectRaw('idUsuario, SUM(pontos) as total')
            ->groupBy('idUsuario')
            ->orderByDesc('total')
            ->with('usuario')
            ->get();
    }
    /**
     * Show the form for creating a new resource.
     */
    public function updatePoints(Request $request): JsonResponse
    {
        $campos = ['pontos', 'usuario'];

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

        Ponto::create([
            'idUsuario' => $request->usuario,
            'pontos' => $request->pontos,
            'acao' => $request->acao,
        ]);

        $user->pontos = $user->pontos + $request->pontos;
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
