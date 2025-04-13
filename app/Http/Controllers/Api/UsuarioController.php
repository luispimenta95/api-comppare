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
use Illuminate\Validation\Rules\Password;
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

        $request->validate([
            'nome' => 'required|string|max:255', // Nome não pode ser vazio, deve ser uma string e ter no máximo 255 caracteres
            'senha' => 'required', 'string',  'max:255', // Senha deve ter no mínimo 8 caracteres
            'cpf' => 'required|string|unique:usuarios,cpf', // CPF é obrigatório, válido e único na tabela de usuários
            'telefone' => 'required|string|size:11', // Telefone deve ser uma string e ter exatamente 11 caracteres (pode ser alterado conforme o formato do seu telefone)
            //'idPlano' => 'required|exists:planos,id', // O idPlano deve existir na tabela planos
            'email' => 'required|email|unique:usuarios,email', // Email obrigatório, deve ser válido e único na tabela de usuários
            //'nascimento' => 'required|date|before:today', // Nascimento obrigatório e deve ser uma data antes de hoje
            //'cardToken' => 'required|string', // cardToken é obrigatório e deve ser uma string
        ]);

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

        //$dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
        $limite = Planos::where('id', 1)->first()->tempoGratuidade;

        $usuario = Usuarios::create([
            'nome' => $request->nome,
            'senha' => bcrypt($request->senha),
            'cpf' => $request->cpf,
            'telefone' => $request->telefone,
            'email' => $request->email,
            'dataNascimento' => '2024-01-01',
            'idPlano' => 1,
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
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id', // Validar se o idUsuario existe
        ]);

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
        $request->validate([
            'nome' => 'required|string|max:255', // Nome não pode ser vazio, deve ser uma string e ter no máximo 255 caracteres
            'email' => 'required|email|unique:usuarios,email', // Email obrigatório, deve ser válido e único na tabela de usuários
            'cpf' => 'required|string|cpf|unique:usuarios,cpf', // CPF obrigatório, válido e único na tabela de usuários
            'telefone' => 'required|string|size:11', // Telefone obrigatório, válido e deve ter 11 caracteres (ajuste conforme seu formato de telefone)
            'nascimento' => 'required|date|before:today', // Nascimento obrigatório, válido como data e anterior a hoje
        ]);

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
        $request->validate([
            'senha' => 'required|string', // Senha obrigatória, deve ser uma string e ter no mínimo 8 caracteres
            'cpf' => 'required|string', // CPF obrigatório, deve ser uma string e validado como CPF (você pode precisar de um pacote para a validação de CPF)
        ]);

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
        $request->validate([
            'cpf' => 'required|string', // CPF obrigatório, deve ser uma string e validado como CPF (você pode precisar de um pacote para a validação de CPF)
        ]);

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
        $request->validate([
            'senha' => 'required', 'string', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(), 'max:255', // Senha deve ter no mínimo 8 caracteres
            'cpf' => 'required|string|unique:usuarios,cpf', // CPF é obrigatório, válido e único na tabela de usuários
        ]);

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
        Helper::relacionarPastas($pasta, $usuario);


    }
}
