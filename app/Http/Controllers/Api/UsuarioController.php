<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convite;
use App\Models\Movimentacoes;
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
use App\Http\Requests\Usuarios\Cadastrar;
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

   public function cadastrarUsuario(Cadastrar $request): JsonResponse
{
    if (!Helper::validaCPF($request->cpf)) {
        return $this->respostaErro(HttpCodesEnum::InvalidCPF);
    }

    if ($this->confirmaUser($request)) {
        return $this->respostaErro(HttpCodesEnum::CPFAlreadyRegistered);
    }

    $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
    $plano = Planos::find($request->idPlano);

    $usuario = Usuarios::create([
        'primeiroNome' => $request->primeiroNome,
        'sobrenome' => $request->sobrenome,
        'apelido' => $request->apelido,
        'senha' => bcrypt($request->senha),
        'cpf' => $request->cpf,
        'telefone' => $request->telefone,
        'email' => $request->email,
        'dataNascimento' => $dataNascimento,
        'idPlano' => $request->idPlano,
        'idPerfil' => Helper::ID_PERFIL_USUARIO,
        'dataLimiteCompra' => now()->addDays($plano->tempoGratuidade)->format('Y-m-d'),
    ]);

    if (!$usuario || !$usuario->id) {
        return $this->respostaErro(HttpCodesEnum::InternalServerError);
    }

    if ($convite = Convite::where('email', $request->email)->first()) {
        $this->associarPastasUsuario($convite, $usuario);
    }

    return response()->json([
        'codRetorno' => HttpCodesEnum::OK->value,
        'message' => HttpCodesEnum::OK->description(),
        'idUser' => $usuario->id
    ]);
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

        return Usuarios::where('cpf', $request->cpf)
            ->orWhere('telefone', $request->telefone)
            ->orWhere('email', $request->email)
            ->exists();
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
            if (Helper::checkDateIsPassed($user->dataLimiteCompra)) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => HttpCodesEnum::ExpiredSubscription->description()
                ]);
            } else {
                $token = JWTAuth::fromUser($user);
                $pastas = $user->pastas->map(function ($pasta) {
                    return [
                        'id' => $pasta->id,
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
                    'dados' => $user,
                    'pastas' => $pastas
                ]);
            }
        }
        return response()->json($response);
    }

    private function associarPastasUsuario(Convite $convite, Usuarios $usuario): void
    {
        $usuario->idPerfil = Helper::ID_PERFIL_CONVIDADO;
        $usuario->idPlano = Helper::ID_PLANO_CONVIDADO;
        $usuario->save();
        $pasta = Pastas::findOrFail($convite->idPasta);
        Helper::relacionarPastas($pasta, $usuario);
    }

    public function atualizarPlanoUsuario(Request $request): JsonResponse
    {
        $campos = ['cpf', 'plano']; // campos nascimento e idPlano devem ser inseridos
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

        $existe = $this->confirmaUser($request);

        if ($existe) {
            $usuario = Usuarios::where('cpf', $request->cpf)->first();
            $plano = Planos::where('id', $usuario->idPlano)->first()->nome;
            $usuario->idPlano = $request->plano;
            $usuario->save();
            $planoNovo = Planos::where('id', $request->plano)->first()->nome;

            Movimentacoes::create([
                'nome_usuario' => $usuario->primeiroNome . ' ' . $usuario->sobrenome,
                'plano_antigo' => $plano,
                'plano_novo' => $planoNovo,
            ]);
            //To do : Verificar permissões de troca de plano
            $response = [
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => HttpCodesEnum::OK->description(),
                'changePlan' => true,

            ];
            if (false) {

                $response = [
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'Operação não permitida.',
                    'changePlan' => false,

                ];
            }
        } else {
            $response = [
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description(),
            ];
        }

        return response()->json($response);
    }


    private function validaSenha(string $senha): bool
    {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $senha) === 1;    }

    public function atualizarSenha(Request $request): JsonResponse
    {
        $request->validate([
            'senha' => 'required|string|min:8',
            'cpf' => 'required|string|exists:usuarios,cpf'
        ]);

        if (!$this->validaSenha($request->senha)) {
            return $this->respostaErro(HttpCodesEnum::BadRequest, [
                'message' => HttpCodesEnum::InvalidPassword->description()
            ]);
        }

        $usuario = Usuarios::where('cpf', $request->cpf)->first();

        if (!$usuario) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        $usuario->senha = bcrypt($request->senha);
        $usuario->save();

        return $this->respostaErro(HttpCodesEnum::OK);
    }

       private function respostaErro(HttpCodesEnum $codigo, array $extras = []): JsonResponse
    {
        $resposta = array_merge([
            'codRetorno' => $codigo->value,
            'message' => $codigo->description()
        ], $extras);

        return response()->json($resposta, $codigo->value);
    }
}
