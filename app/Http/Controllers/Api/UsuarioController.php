<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convite;
use App\Models\Pastas;
use App\Models\Planos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use Illuminate\Support\Facades\Hash;
use App\Http\Util\Helper;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Enums\HttpCodesEnum;

class UsuarioController extends Controller
{
    private HttpCodesEnum $messages;

    public function __construct()
    {
        $this->messages = HttpCodesEnum::OK;  // Usando a enum para um valor inicial
    }

    public function index(): JsonResponse
    {
        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
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
            $this->messages = HttpCodesEnum::MissingRequiredFields;

            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $this->messages->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        if (!Helper::validaCPF($request->cpf)) {
            $this->messages = HttpCodesEnum::InvalidCPF;
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $this->messages->description()
            ];
            return response()->json($response);
        }

        if ($this->confirmaUser($request)) {
            $this->messages = HttpCodesEnum::CPFAlreadyRegistered;
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $this->messages->description()
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

            $convite = Convite::where('email', $request->email)->firstOrFail();
            if($convite){
                $this->associarPastasUsuario($convite, $usuario);
            }

            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description()
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description()
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
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::find($request->idUsuario);

        $response = isset($usuario->id) ? [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'data' => $usuario
        ] : [
            'codRetorno' => HttpCodesEnum::NotFound->value,
            'message' => HttpCodesEnum::NotFound->description()
        ];

        return response()->json($response);
    }

    public function atualizarDados(Request $request): JsonResponse
    {
        $campos = ['nome', 'senha', 'email', 'cpf', 'telefone', 'nascimento'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        if (!Helper::validaCPF($request->cpf)) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::InvalidCPF->description(),
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
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
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
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
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
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $user = Usuarios::where('cpf', $request->cpf)->first();

        if (!$user) {
            $response = [
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description(),
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
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::where('cpf', $request->cpf)->first();

        return isset($usuario->id) ? true : false;
    }

    public function validaExistenciaUsuario(Request $request): JsonResponse
    {
        $existe = $this->confirmaUser($request);

        $response = $existe ? [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
        ] : [
            'codRetorno' => HttpCodesEnum::NotFound->value,
            'message' => HttpCodesEnum::NotFound->description(),
        ];

        return response()->json($response);
    }

    public function atualizarSenha(Request $request): JsonResponse
    {
        $campos = ['cpf', 'senha'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::findOrFail($request->cpf);

        if (isset($usuario->cpf)) {
            $usuario->senha = bcrypt($request->senha);
            $usuario->save();

            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
            ];
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => HttpCodesEnum::InternalServerError->description(),
            ];
        }

        return response()->json($response);
    }

    private function checaPermissoes(Usuarios $user, Request $request): JsonResponse
    {
        $response = [];

        if (!$user || !Hash::check($request->input('senha'), $user->senha)) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description()
            ]);
        }

        if ($user) {
            if ($user->status == 0) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => HttpCodesEnum::UserBlockedDueToInactivity->description()
                ]);
            }
            $plano = Planos::where('id', $user->idPlano)->first();
            $diasRenovacao = $plano->tempoGratuidade;
            if ($plano->idHost == null) {
                $dataLimiteCompra = Carbon::parse($user->created_at)->addDays($diasRenovacao);
            } else {
                $dataLimiteCompra = $user->dataUltimoPagamento != null ?
                    Carbon::parse($user->dataUltimoPagamento)->addDays($diasRenovacao) :
                    Carbon::parse($user->created_at)->addDays(Helper::TEMPO_GRATUIDADE);
            }

            if (Helper::checkDateIsPassed($dataLimiteCompra)) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => HttpCodesEnum::ExpiredSubscription->description()
                ]);
            } else {
                $token = JWTAuth::fromUser($user);
                $pastas = $user->pastas->map(function ($pasta) {
                    return [
                        'nome' => $pasta->nome,
                        'caminho' => $pasta->caminho
                    ];
                });
                $user->ultimoAcesso = Carbon::now();
                $user->save();


                return response()->json([
                    'codRetorno' => HttpCodesEnum::OK->value,
                    'message' => HttpCodesEnum::OK->description(),
                    'token' => $token,
                    'dados' => $user->only('id', 'nome', 'cpf', 'telefone'),
                    'pastas' => $pastas
                ]);
            }
        }
        return response()->json($response);
    }

    private function associarPastasUsuario(Convite $convite, Usuarios $usuario):void
    {
        $usuario->idPerfil = Helper::ID_PERFIL_CONVIDADO;
        $usuario->idPlano = Helper::ID_PLANO_CONVIDADO;
        $usuario->save();
        $pasta = Pastas::findOrFail($convite->idPasta);

        // Associa o criador da pasta à pasta
        $pasta->usuarios()->attach($convite->idUsuario);

        // Associa o usuário que recebeu o convite à pasta
        $pasta->usuarios()->attach($usuario->id);


    }
}
