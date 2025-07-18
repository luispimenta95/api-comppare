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
use App\Models\CodeEmailVerify;
use App\Models\Convite;
use App\Models\Movimentacoes;
use App\Models\Pastas;
use App\Models\Planos;
use App\Models\Usuarios;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailForgot;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Controller para gerenciamento de usuários
 * 
 * Responsável por todas as operações relacionadas aos usuários do sistema,
 * incluindo cadastro, autenticação, atualização de dados, gerenciamento de planos,
 * alteração de senhas e funcionalidades de recuperação de senha.
 */
class UsuarioController extends Controller
{
    /**
     * Atualiza os dados pessoais de um usuário
     * 
     * Valida CPF, formata data de nascimento e atualiza informações
     * pessoais do usuário incluindo nome, senha, telefone e email.
     * 
     * @param AtualizarDadosRequest $request - Dados validados do usuário
     * @return JsonResponse - Confirmação da atualização ou erro
     */
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

    /**
     * Atualiza o plano de assinatura de um usuário
     * 
     * Altera o plano do usuário e registra a movimentação financeira
     * associada à mudança de plano.
     * 
     * @param AtualizarPlanoUsuarioRequest $request - ID do usuário e novo plano
     * @return JsonResponse - Confirmação da alteração ou erro
     */
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

    /**
     * Atualiza a senha de um usuário
     * 
     * Verifica se a senha atual está correta antes de definir
     * a nova senha criptografada.
     * 
     * @param Request $request - ID do usuário, senha atual e nova senha
     * @return JsonResponse - Confirmação da alteração ou erro de validação
     */
    public function atualizarSenha(Request $request): JsonResponse
    {
        $usuario = Usuarios::where('email', $request->email)->first();

        if (!$usuario) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        // Busca o último token
        $registro = DB::table('password_reset_tokens')
            ->where('email', $usuario->email)
            ->latest('created_at')
            ->first();


        // Verifica se existe token válido e se o código enviado bate
        if (
            !$registro ||
            Carbon::parse($registro->created_at)->diffInMinutes(now()) > 60 ||
            !Hash::check($request->codigo, $registro->token)
        ) {
            return $this->respostaErro(
                HttpCodesEnum::Unauthorized,
                [
                    'message' => 'Código inválido ou expirado. Por favor, solicite um novo código.'
                ]
            );
        }

        if (!$this->validaSenha($request->senha)) {
            return $this->respostaErro(HttpCodesEnum::BadRequest, [
                'message' => HttpCodesEnum::InvalidPassword->description()
            ]);
        }

        // Atualiza a senha
        $usuario->senha = bcrypt($request->senha);
        $usuario->save();

        // Remove o token usado (opcional, mas recomendado)
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Senha atualizada com sucesso.'
        ]);
    }

    /**
     * Atualiza o status de um usuário
     * 
     * Permite ativar ou desativar um usuário no sistema.
     * 
     * @param AtualizarStatusRequest $request - ID do usuário e novo status
     * @return JsonResponse - Confirmação da alteração
     */
    public function atualizarStatus(AtualizarStatusRequest $request): JsonResponse
    {
        $usuario = Usuarios::findOrFail($request->idUsuario);
        $usuario->status = $request->status;
        $usuario->save();

        return $this->respostaErro(HttpCodesEnum::OK);
    }

    /**
     * Autentica um usuário no sistema
     * 
     * Valida as credenciais e retorna um token JWT para autenticação
     * nas próximas requisições à API.
     * 
     * @param AutenticarUsuarioRequest $request - Email e senha do usuário
     * @return JsonResponse - Token JWT e dados do usuário ou erro de autenticação
     */
    public function autenticar(AutenticarUsuarioRequest $request): JsonResponse
    {
        $user = Usuarios::with(['pastas.photos'])->where('cpf', $request->cpf)->first();

        if (!$user) {
            return $this->respostaErro(HttpCodesEnum::NotFound);
        }

        return $this->checaPermissoes($user, $request);
    }

    /**
     * Cadastra um novo usuário no sistema
     * 
     * Valida dados pessoais, cria conta do usuário, envia email de boas-vindas
     * e processa convites pendentes se existirem.
     * 
     * @param Cadastrar $request - Dados completos do novo usuário
     * @return JsonResponse - Dados do usuário criado e token JWT ou erro
     */
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
        // Gera o token JWT para o usuário recém-criado
        $token = JWTAuth::fromUser($usuario);
        
        return response()->json([
            'codRetorno' => HttpCodesEnum::Created->value,
            'message'    => HttpCodesEnum::Created->description(),
            'usuario'       => $usuario,
            'token'         => $token
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

        // Extrai apenas os dados relevantes das pastas com suas imagens
        $pastas = $user->pastas->map(fn($pasta) => [
            'id' => $pasta->id,
            'nome' => $pasta->nome,
            'caminho' => $pasta->caminho,
            'imagens' => $pasta->photos->map(fn($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'taken_at' => $photo->taken_at
            ])->values()
        ])->values(); // garante índice limpo (0,1,2...)

        // Atualiza último acesso
        $user->ultimoAcesso = now();
        $user->save();

        // Converte o usuário para array e remove a relação 'pastas'
        $dadosUsuario = $user->toArray();
        unset($dadosUsuario['pastas']);

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'token' => $token,
            'dados' => $dadosUsuario,
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

    /**
     * Recupera informações de um usuário específico
     * 
     * Busca e retorna os dados completos de um usuário pelo ID.
     * 
     * @param GetUserRequest $request - ID do usuário
     * @return JsonResponse - Dados completos do usuário ou erro se não encontrado
     */
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

    /**
     * Lista usuários com paginação e filtros
     * 
     * Retorna lista paginada de usuários com possibilidade de filtrar
     * por diferentes critérios conforme parâmetros fornecidos.
     * 
     * @param IndexUsuarioRequest $request - Parâmetros de paginação e filtros
     * @return JsonResponse - Lista paginada de usuários
     */
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

    /**
     * Valida se um usuário existe no sistema
     * 
     * Verifica a existência de um usuário por email ou outros critérios.
     * 
     * @param ValidaExistenciaUsuarioRequest $request - Dados para validação
     * @return JsonResponse - Confirmação da existência ou não do usuário
     */
    public function validaExistenciaUsuario(ValidaExistenciaUsuarioRequest $request): JsonResponse
    {
        $existe = $this->confirmaUser($request);

        return response()->json([
            'codRetorno' => $existe ? HttpCodesEnum::OK->value : HttpCodesEnum::NotFound->value,
            'message' => $existe ? HttpCodesEnum::OK->description() : HttpCodesEnum::NotFound->description(),
        ]);
    }

    /**
     * Processa solicitação de recuperação de senha
     * 
     * Gera código de verificação e envia email com instruções
     * para redefinição de senha do usuário.
     * 
     * @param Request $request - Email do usuário para recuperação
     * @return JsonResponse - Confirmação do envio do email ou erro
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $usuario = Usuarios::where('email', $request->email)->first();

        if (!$usuario) {
            // Resposta genérica para não vazar info
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Usuário não encontrado.',
            ]);
        }

        // Busca o último token
        $ultimoToken = DB::table('password_reset_tokens')
            ->where('email', $usuario->email)
            ->latest('created_at')
            ->first();

        // Se foi gerado há menos de 5 minutos, não faz nada
        if ($ultimoToken && Carbon::parse($ultimoToken->created_at)->diffInMinutes(now()) < 1) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::TooManyRequests->value,
                'message' => 'Por favor, aguarde 5 minutos antes de solicitar um novo código.',
            ]);
        }

        $codigo = CodeEmailVerify::generateCode();

        // Remove tokens anteriores
        DB::table('password_reset_tokens')->where('email', $usuario->email)->delete();

        // Salva novo token com hash
        DB::table('password_reset_tokens')->insert([
            'email' => $usuario->email,
            'token' => Hash::make($codigo),
            'created_at' => now(),
        ]);

        // Envia e-mail
        $dadosEmail = [
            'to' => $usuario->email,
            'body' => [
                'nome' => $usuario->primeiroNome,
                'code' => $codigo
            ]
        ];

        Mail::to($usuario->email)->send(new EmailForgot($dadosEmail));

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Se o e-mail estiver cadastrado, você receberá um código de recuperação de senha no seu e-mail.',
        ]);
    }
}
