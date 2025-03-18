<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Planos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use Illuminate\Support\Facades\Hash;
use App\Http\Util\Helper;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuarioController extends Controller
{
    private array $codes;

    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
    }

    public function index(): JsonResponse
    {

        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200],
            'totalUsuarios' => Usuarios::count(),
            'usuariosAtivos' => Usuarios::where('status', 1)->count(),
            'data' => Usuarios::all()
        ];

        return response()->json($response);
    }

    public function cadastrarUsuario(Request $request): JsonResponse
    {
        $campos = ['nome', 'senha', 'cpf', 'telefone', 'idPlano', 'email', 'nascimento', 'cardToken'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        if (!Helper::validaCPF($request->cpf)) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-2]
            ];
            return response()->json($response);
        }

        if ($this->confirmaUser($request)) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-6]
            ];
            return response()->json($response);
        }

        $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
        $limite = Planos::where('id', $request->idPlano)->first()->tempoGratuidade;

        $usuario = Usuarios::create([
            'nome' => $request->nome,
            'senha' => bcrypt($request->senha),
            'cpf' => $request->cpf,
            'telefone' => $request->telefone,
            'email' => $request->email,
            'dataNascimento' => $dataNascimento,
            'idPlano' => $request->idPlano,
            'idPerfil' => Helper::ID_PERFIL_USUARIO,
            'dataLimiteCompra' => Carbon::now()->addDays($limite)->format('Y-m-d')
        ]);

        if (isset($usuario->id)) {
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500]
            ];
        }

        return response()->json($response);
    }

    public function getUser(Request $request): JsonResponse
    {
        $campos = ['idUsuario'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::find($request->idUsuario);

        $response = isset($usuario->id) ? [
            'codRetorno' => 200,
            'message' => $this->codes[200],
            'data' => $usuario
        ] : [
            'codRetorno' => 404,
            'message' => $this->codes[404]
        ];

        return response()->json($response);
    }

    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['nome', 'senha', 'email', 'cpf', 'telefone', 'nascimento'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        if (!Helper::validaCPF($request->cpf)) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[400],
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::findOrFail($request->idUsuario);
        $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');

        if (isset($usuario->id)) {
            $usuario->nome = $request->nome;
            $usuario->senha = bcrypt($request->senha);
            $usuario->cpf = $request->cpf;
            $usuario->telefone = $request->telefone;
            $usuario->email = $request->email;
            $usuario->dataNascimento = $dataNascimento;
            $usuario->save();

            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200],
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500],
            ];
        }

        return response()->json($response);
    }

    public function atualizarStatus(Request $request): JsonResponse
    {
        $usuario = Usuarios::findOrFail($request->idUsuario);

        if (isset($usuario->id)) {
            $usuario->status = $request->status;
            $usuario->save();

            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200],
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500],
            ];
        }

        return response()->json($response);
    }

    public function autenticar(Request $request): JsonResponse
    {
        $campos = ['cpf', 'senha'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $user = Usuarios::where('cpf', $request->cpf)->first();

        if (!$user) {
            $response = [
                'codRetorno' => 404,
                'message' => $this->codes[404],
            ];
            return response()->json($response);
        }

        $response = $this->checaPermissoes($user, $request);
        return response()->json($response);
    }

    private function confirmaUser(Request $request): mixed
    {
        $campos = ['cpf'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $exists = Usuarios::findOrFail($request->cpf);
        return $exists;
    }

    public function validaExistenciaUsuario(Request $request): JsonResponse
    {
        $existe = $this->confirmaUser($request);

        $response = $existe ? [
            'codRetorno' => 200,
            'message' => $this->codes[200],
        ] : [
            'codRetorno' => 404,
            'message' => $this->codes[404],
        ];

        return response()->json($response);
    }

    public function atualizarSenha(Request $request): JsonResponse
    {
        $campos = ['cpf', 'senha'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => 400,
                'message' => $this->codes[-9],
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::findOrFail($request->cpf);

        if (isset($usuario->cpf)) {
            $usuario->senha = bcrypt($request->senha);
            $usuario->save();

            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200],
            ];
        } else {
            $response = [
                'codRetorno' => 500,
                'message' => $this->codes[500],
            ];
        }

        return response()->json($response);
    }

    private function checaPermissoes(Usuarios $user, Request $request): JsonResponse
    {
        $response = [];

        if (!$user || !Hash::check($request->input('senha'), $user->senha)) {
            return response()->json([
                'codRetorno' => 404,
                'message' => $this->codes[404]
            ]);
        }

        if ($user) {
            $plano = Planos::where('id', $user->idPlano)->first();
            $diasRenovacao = $plano->tempoGratuidade;
            if ($plano->idHost == null) {
                $dataLimiteCompra = Carbon::parse($user->created_at)->addDays($diasRenovacao);
            } else {
                $dataLimiteCompra = $user->dataUltimoPagamento != null ?
                    Carbon::parse($user->dataUltimoPagamento)->addDays($diasRenovacao) :
                    Carbon::parse($user->created_at)->addDays(Helper::TEMPO_GRATUIDADE);
            }

            $dataAtual = Carbon::now();

            if ($dataLimiteCompra->lt($dataAtual)) {
                return response()->json([
                    'codRetorno' => 400,
                    'message' => $this->codes[-8]
                ]);
            } else {
                $token = JWTAuth::fromUser($user);
                $pastas = $user->pastas->map(function ($pasta) {
                    return [
                        'nome' => $pasta->nome,
                        'caminho' => $pasta->caminho
                    ];
                });

                return response()->json([
                    'codRetorno' => 200,
                    'message' => $this->codes[200],
                    'token' => $token,
                    'dados' => $user->only('id', 'nome', 'cpf', 'telefone'),
                    'pastas' => $pastas
                ]);
            }
        }
        return response()->json($response);
    }
}
