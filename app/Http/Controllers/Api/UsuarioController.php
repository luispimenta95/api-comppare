<?php

namespace App\Http\Controllers\Api;

use App\Enums\HttpCodesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\AtualizarDadosRequest;
use App\Http\Requests\Usuarios\AtualizarPlanoUsuarioRequest;
use App\Http\Requests\Usuarios\AtualizarSenhaRequest;
use App\Http\Requests\Usuarios\AtualizarStatusRequest;
use App\Http\Requests\Usuarios\AutenticarUsuarioRequest;
use App\Http\Requests\Usuarios\Cadastrar;
use App\Http\Requests\Usuarios\IndexUsuarioRequest;
use App\Http\Requests\Usuarios\GetUserRequest;
use App\Http\Requests\Usuarios\ValidaExistenciaUsuarioRequest;
use App\Http\Util\Helper;
use App\Models\Convite;
use App\Models\Movimentacoes;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Usuarios;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsuarioController extends Controller
{
    public function atualizarDados(AtualizarDadosRequest $request): JsonResponse
    {
        if (!Helper::validaCPF($request->cpf)) {
            return $this->respostaErro(HttpCodesEnum::BadRequest, [
                'message' => HttpCodesEnum::InvalidCPF->description(),
            ]);
        }

        $usuario = Usuarios::findOrFail($request->idUsuario);
        $dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');

        $usuario->nome = $request->nome;
        $usuario->senha = bcrypt($request->senha);
        $usuario->cpf = $request->cpf;
        $usuario->telefone = $request->telefone;
        $usuario->email = $request->email;
        $usuario->dataNascimento = $dataNascimento;
        $usuario->save();

        return $this->respostaErro(HttpCodesEnum::OK);
    }

    public function atualizarPlanoUsuario(AtualizarPlanoUsuarioRequest $request): JsonResponse
    {
        $usuario = Usuarios::where('cpf', $request->cpf)->first();

        if (!$usuario) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        $planoAntigo = Planos::find($usuario->idPlano)->nome;
        $usuario->idPlano = $request->plano;
        $usuario->save();
        $planoNovo = Planos::find($request->plano)->nome;

        Movimentacoes::create([
            'nome_usuario' => $usuario->primeiroNome . ' ' . $usuario->sobrenome,
            'plano_antigo' => $planoAntigo,
            'plano_novo' => $planoNovo,
        ]);

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'changePlan' => true,
        ]);
    }

    public function atualizarSenha(AtualizarSenhaRequest $request): JsonResponse
    {
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

    public function atualizarStatus(AtualizarStatusRequest $request): JsonResponse
    {
        $usuario = Usuarios::findOrFail($request->idUsuario);
        $usuario->status = $request->status;
        $usuario->save();

        return $this->respostaErro(HttpCodesEnum::OK);
    }

    public function autenticar(AutenticarUsuarioRequest $request): JsonResponse
    {
        $user = Usuarios::where('cpf', $request->cpf)->first();

        if (!$user) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        return $this->checaPermissoes($user, $request);
    }

    public function cadastrarUsuario(Cadastrar $request): JsonResponse
    {
        if ($this->confirmaUser($request)) {
            return $this->respostaErro(HttpCodesEnum::Conflict, [
                'message' => 'Usuário já cadastrado com os dados informados.',
            ]);
        }
        $limite = Planos::where('id', $request->idPlano)->first()->tempoGratuidade;
        
        $usuario = Usuarios::create([
            'primeiroNome'     => $request->primeiroNome,
            'sobrenome'        => $request->sobrenome,
            'apelido'          => $request->apelido,
            'cpf'              => $request->cpf,
            'senha'            => bcrypt($request->senha),
            'telefone'         => $request->telefone,
            'email'            => $request->email,
            'idPlano'          => $request->idPlano,
            'dataNascimento'   => Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d'),
            'idPerfil' => Helper::ID_PERFIL_USUARIO,
            'dataLimiteCompra' => Carbon::now()->addDays($limite)->format('Y-m-d')
        ]);

        if (isset($usuario->id)) {
            $convite = Convite::where('email', $request->email)->first();

            if ($convite) {
                $this->associarPastasUsuario($convite, $usuario);
            }
        }

        return response()->json([
            'codRetorno' => HttpCodesEnum::Created->value,
            'message'    => HttpCodesEnum::Created->description(),
            'data'       => $usuario
        ], HttpCodesEnum::Created->value);
    }

    private function associarPastasUsuario(Convite $convite, Usuarios $usuario): void
    {
        $usuario->idPerfil = Helper::ID_PERFIL_CONVIDADO;
        $usuario->idPlano = Helper::ID_PLANO_CONVIDADO;
        $usuario->save();

        $pasta = Pastas::findOrFail($convite->idPasta);
        Helper::relacionarPastas($pasta, $usuario);
    }

    private function checaPermissoes(Usuarios $user, AutenticarUsuarioRequest $request): JsonResponse
    {
        if (!Hash::check($request->input('senha'), $user->senha)) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        if ($user->status === 0) {
            return $this->respostaErro(HttpCodesEnum::BadRequest, [
                'message' => HttpCodesEnum::UserBlockedDueToInactivity->description()
            ]);
        }

        if (Helper::checkDateIsPassed($user->dataLimiteCompra)) {
            return $this->respostaErro(HttpCodesEnum::BadRequest, [
                'message' => HttpCodesEnum::ExpiredSubscription->description()
            ]);
        }

        $token = JWTAuth::fromUser($user);
        $pastas = $user->pastas->map(fn($pasta) => [
            'id' => $pasta->id,
            'nome' => $pasta->nome,
            'caminho' => $pasta->caminho
        ]);

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

    private function confirmaUser(object $request): bool
    {
        return Usuarios::where('cpf', $request->cpf)
            ->orWhere('telefone', $request->telefone)
            ->orWhere('email', $request->email)
            ->exists();
    }

    public function getUser(GetUserRequest $request): JsonResponse
    {
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

    public function index(IndexUsuarioRequest $request): JsonResponse
    {
        $query = Usuarios::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('nome')) {
            $query->where('primeiroNome', 'like', '%' . $request->input('nome') . '%');
        }

        $usuarios = $query->get();

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'totalUsuarios' => Usuarios::count(),
            'usuariosAtivos' => Usuarios::where('status', 1)->count(),
            'data' => $usuarios
        ];

        return response()->json($response);
    }

    private function respostaErro(HttpCodesEnum $codigo, array $extras = []): JsonResponse
    {
        $resposta = array_merge([
            'codRetorno' => $codigo->value,
            'message' => $codigo->description()
        ], $extras);

        $statusHttp = $codigo->value > 0 ? $codigo->value : 400;
        return response()->json($resposta, $statusHttp);
    }

    private function validaSenha(string $senha): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $senha) === 1;
    }

    public function validaExistenciaUsuario(ValidaExistenciaUsuarioRequest $request): JsonResponse
    {
        $existe = $this->confirmaUser($request);

        return response()->json([
            'codRetorno' => $existe ? HttpCodesEnum::OK->value : HttpCodesEnum::NotFound->value,
            'message' => $existe ? HttpCodesEnum::OK->description() : HttpCodesEnum::NotFound->description(),
        ]);
    }
}
