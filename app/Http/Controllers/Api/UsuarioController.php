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
use App\Models\Tag;
use App\Models\Usuarios;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailForgot;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Recupera as pastas e subpastas do usuário em estrutura hierárquica
     *
     * @param Usuarios $user
     * @param array $limitesInfo
     * @return array
     */
    public function getPastasEstruturadas(int $idUsuario): array
    {
        $usuario = Usuarios::findOrFail($idUsuario);
        $todasPastas = $usuario->pastas()->with(['photos', 'subpastas.photos'])->get();

        $pastasPrincipais = $todasPastas->whereNull('idPastaPai');

        $pastas = $pastasPrincipais->map(function ($pasta){
            $subpastas = $pasta->subpastas->map(function ($subpasta) {
                return [
                    'id' => $subpasta->id,
                    'nome' => $subpasta->nome,
                    'path' => Helper::formatFolderUrl($subpasta),
                    'idPastaPai' => $subpasta->idPastaPai,
                    'imagens' => $subpasta->photos->map(fn($photo) => [
                        'id' => $photo->id,
                        'path' => Helper::formatImageUrl($photo->path),
                        'taken_at' => $photo->taken_at
                    ])->values()
                ];
            })->values();

            return [
                'nome' => $pasta->nome,
                'id' => $pasta->id,
                'path' => Helper::formatFolderUrl($pasta),
                'idPastaPai' => null,
                'subpastas' => $subpastas
            ];
        })->values();

        return $pastas->toArray();
    }
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
     * Atualiza campos de um usuário (campo por campo, todos opcionais exceto idUsuario)
     *
     * Request esperado (exemplos):
     * {
     *   "idUsuario": 1,
     *   "primeiroNome": "NovoNome",
     *   "sobrenome": "NovoSobrenome",
     *   "apelido": "novoapelido",
     *   "cpf": "12345678909",
     *   "email": "novo@email.com",
     *   "telefone": "61999999999",
     *   "nascimento": "01/01/1990",
     *   "senha": "NovaSenha@123",
     *   "idPlano": 2
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function atualizarUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id',
            'primeiroNome' => 'sometimes|string|max:255',
            'sobrenome' => 'sometimes|string|max:255',
            'apelido' => 'sometimes|nullable|string|max:255',
            'cpf' => 'sometimes|string',
            'email' => 'sometimes|email|max:255',
            'telefone' => 'sometimes|string|max:20',
            'nascimento' => 'sometimes|date_format:d/m/Y',
            'senha' => 'sometimes|string|min:8',
            'idPlano' => 'sometimes|integer|exists:planos,id'
        ]);

        try {
            $usuario = Usuarios::findOrFail($request->idUsuario);

            // CPF: valida formato e unicidade
            if ($request->filled('cpf')) {
                if (!Helper::validaCPF($request->cpf)) {
                    return $this->respostaErro(HttpCodesEnum::BadRequest, ['message' => HttpCodesEnum::InvalidCPF->description()]);
                }
                $existsCpf = Usuarios::where('cpf', $request->cpf)->where('id', '!=', $usuario->id)->exists();
                if ($existsCpf) {
                    return $this->respostaErro(HttpCodesEnum::Conflict, ['message' => 'CPF já está em uso por outro usuário.']);
                }
                $usuario->cpf = $request->cpf;
            }

            // Email: unicidade
            if ($request->filled('email')) {
                $existsEmail = Usuarios::where('email', $request->email)->where('id', '!=', $usuario->id)->exists();
                if ($existsEmail) {
                    return $this->respostaErro(HttpCodesEnum::Conflict, ['message' => 'E-mail já está em uso por outro usuário.']);
                }
                $usuario->email = $request->email;
            }

            if ($request->filled('primeiroNome')) {
                $usuario->primeiroNome = $request->primeiroNome;
            }
            if ($request->filled('sobrenome')) {
                $usuario->sobrenome = $request->sobrenome;
            }
            if ($request->has('apelido')) {
                $usuario->apelido = $request->apelido;
            }
            if ($request->filled('telefone')) {
                $usuario->telefone = $request->telefone;
            }

            if ($request->filled('nascimento')) {
                $usuario->dataNascimento = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
            }

            if ($request->filled('senha')) {
                if (!$this->validaSenha($request->senha)) {
                    return $this->respostaErro(HttpCodesEnum::BadRequest, ['message' => HttpCodesEnum::InvalidPassword->description()]);
                }
                $usuario->senha = bcrypt($request->senha);
            }

            if ($request->filled('idPlano')) {
                $usuario->idPlano = $request->idPlano;
            }

            $usuario->save();

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Usuário atualizado com sucesso.',
                'usuario' => $usuario
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar usuário', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->respostaErro(HttpCodesEnum::InternalServerError, ['message' => 'Erro interno ao atualizar usuário.']);
        }
    }

    /**
     * Atualiza o plano de assinatura de um usuário
     * 
     * Altera o plano do usuário e registra a movimentação financeira
     * associada à mudança de plano.
     * 
     * Implementa validações para upgrade/downgrade:
     * - Verifica se o plano de destino existe e está ativo
     * - Valida se o downgrade é possível (uso atual não excede limites do novo plano)
     * - Permite upgrades sempre
     * - Trata planos especiais (convidados, filiados)
     * 
     * @param AtualizarPlanoUsuarioRequest $request - ID do usuário e novo plano
     * @return JsonResponse - Confirmação da alteração ou erro
     */
    public function atualizarPlanoUsuario(AtualizarPlanoUsuarioRequest $request): JsonResponse
    {
        try {
            $usuario = Usuarios::with('plano')->where('cpf', $request->cpf)->first();

            if (!$usuario) {
                return $this->respostaErro(HttpCodesEnum::NotFound, ['message' => 'Usuário não encontrado']);
            }

            // Verificar se é o mesmo plano atual
            if ($usuario->idPlano == $request->plano) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'O usuário já possui este plano',
                    'changePlan' => false,
                ], 400);
            }

            // Buscar plano de destino
            $planoNovo = Planos::find($request->plano);
            if (!$planoNovo || !$planoNovo->status) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'Plano de destino não está disponível',
                    'changePlan' => false,
                ], 400);
            }

            $planoAtual = $usuario->plano;

            // Validar regras específicas de mudança de plano
            $validacao = $this->validarMudancaPlano($usuario, $planoAtual, $planoNovo);
            if (!$validacao['permitido']) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => $validacao['motivo'],
                    'changePlan' => false,
                    'detalhes' => $validacao['detalhes'] ?? null,
                ], 400);
            }

            // Realizar a mudança de plano
            $planoAntigoNome = $planoAtual->nome;
            $usuario->idPlano = $request->plano;
            $usuario->save();

            // Registrar movimentação
            Movimentacoes::create([
                'nome_usuario' => $usuario->primeiroNome . ' ' . $usuario->sobrenome,
                'plano_antigo' => $planoAntigoNome,
                'plano_novo' => $planoNovo->nome,
            ]);

            Log::info("Plano alterado com sucesso", [
                'usuario_id' => $usuario->id,
                'cpf' => $usuario->cpf,
                'plano_antigo' => $planoAntigoNome,
                'plano_novo' => $planoNovo->nome,
                'valor_antigo' => $planoAtual->valor,
                'valor_novo' => $planoNovo->valor,
            ]);

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Plano alterado com sucesso',
                'changePlan' => true,
                'plano_anterior' => $planoAntigoNome,
                'plano_atual' => $planoNovo->nome,
                'tipo_alteracao' => $validacao['tipo'],
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao alterar plano do usuário", [
                'cpf' => $request->cpf,
                'plano_destino' => $request->plano,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => 'Erro interno do servidor ao alterar plano',
                'changePlan' => false,
            ], 500);
        }
    }

    /**
     * Valida se a mudança de plano é permitida
     * 
     * @param Usuarios $usuario
     * @param Planos $planoAtual
     * @param Planos $planoNovo
     * @return array
     */
    private function validarMudancaPlano(Usuarios $usuario, Planos $planoAtual, Planos $planoNovo): array
    {
        // Planos especiais que têm regras específicas
        $planosEspeciais = [
            Helper::ID_PLANO_CONVIDADO, // Plano de Convidados
            2, // Plano de Filiados
        ];

        // Se o usuário está em um plano de convidado, só pode ir para planos pagos específicos
        if ($usuario->idPlano == Helper::ID_PLANO_CONVIDADO) {
            if (in_array($planoNovo->id, $planosEspeciais)) {
                return [
                    'permitido' => false,
                    'motivo' => 'Convidados não podem migrar para planos especiais',
                    'tipo' => 'restricao_convidado'
                ];
            }
        }

        // Se está tentando migrar para plano de convidado ou filiado, verificar restrições
        if (in_array($planoNovo->id, $planosEspeciais)) {
            return [
                'permitido' => false,
                'motivo' => 'Não é possível migrar para planos especiais através desta operação',
                'tipo' => 'restricao_plano_especial'
            ];
        }

        // Determinar se é upgrade ou downgrade baseado no valor
        $isUpgrade = $planoNovo->valor > $planoAtual->valor;
        $isDowngrade = $planoNovo->valor < $planoAtual->valor;

        // Upgrades são sempre permitidos
        if ($isUpgrade) {
            return [
                'permitido' => true,
                'tipo' => 'upgrade',
                'motivo' => 'Upgrade permitido'
            ];
        }

        // Para downgrades, verificar se o uso atual está dentro dos limites do novo plano
        if ($isDowngrade) {
            return $this->validarDowngrade($usuario, $planoNovo);
        }

        // Mudança entre planos de mesmo valor (lateral)
        return [
            'permitido' => true,
            'tipo' => 'lateral',
            'motivo' => 'Mudança lateral de plano permitida'
        ];
    }

    /**
     * Valida se o downgrade é possível verificando o uso atual do usuário
     * 
     * @param Usuarios $usuario
     * @param Planos $planoNovo
     * @return array
     */
    private function validarDowngrade(Usuarios $usuario, Planos $planoNovo): array
    {
        $detalhes = [];
        $restricoes = [];

        // Verificar uso de pastas principais criadas no mês atual
        $pastasPrincipaisCriadasMes = Pastas::where('idUsuario', $usuario->id)
            ->whereNull('idPastaPai') // Apenas pastas principais
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($pastasPrincipaisCriadasMes > $planoNovo->quantidadePastas) {
            $restricoes[] = "Pastas principais criadas este mês: {$pastasPrincipaisCriadasMes} (limite do novo plano: {$planoNovo->quantidadePastas})";
            $detalhes['pastas_principais_criadas_mes'] = $pastasPrincipaisCriadasMes;
            $detalhes['limite_pastas_novo'] = $planoNovo->quantidadePastas;
        }

        // Verificar total de pastas principais do usuário
        $totalPastasPrincipais = $usuario->pastas()->whereNull('idPastaPai')->count();
        if ($totalPastasPrincipais > $planoNovo->quantidadePastas) {
            $restricoes[] = "Total de pastas principais: {$totalPastasPrincipais} (limite do novo plano: {$planoNovo->quantidadePastas})";
            $detalhes['total_pastas_principais'] = $totalPastasPrincipais;
        }

        // Verificar se alguma pasta principal excede o limite de subpastas do novo plano
        $pastasComExcessoSubpastas = Pastas::where('idUsuario', $usuario->id)
            ->whereNull('idPastaPai')
            ->withCount('subpastas')
            ->having('subpastas_count', '>', $planoNovo->quantidadeSubpastas)
            ->get();

        if ($pastasComExcessoSubpastas->isNotEmpty()) {
            $detalhesSubpastas = [];
            foreach ($pastasComExcessoSubpastas as $pasta) {
                $detalhesSubpastas[] = "'{$pasta->nome}' tem {$pasta->subpastas_count} subpastas";
            }
            $restricoes[] = "Algumas pastas excedem o limite de subpastas do novo plano ({$planoNovo->quantidadeSubpastas}): " . implode(', ', $detalhesSubpastas);
            $detalhes['pastas_excesso_subpastas'] = $pastasComExcessoSubpastas->pluck('nome', 'subpastas_count');
            $detalhes['limite_subpastas_novo'] = $planoNovo->quantidadeSubpastas;
        }

        // Verificar tags criadas pelo usuário
        $totalTags = $usuario->tags()->count();
        if ($totalTags > $planoNovo->quantidadeTags) {
            $restricoes[] = "Total de tags: {$totalTags} (limite do novo plano: {$planoNovo->quantidadeTags})";
            $detalhes['total_tags'] = $totalTags;
            $detalhes['limite_tags_novo'] = $planoNovo->quantidadeTags;
        }

        // Verificar fotos (se houver uma forma de contar)
        // Note: Assumindo que há uma relação ou forma de contar fotos por usuário
        // Isso pode ser implementado conforme a estrutura do banco

        if (!empty($restricoes)) {
            return [
                'permitido' => false,
                'motivo' => 'Downgrade não permitido: uso atual excede limites do novo plano',
                'tipo' => 'downgrade_bloqueado',
                'detalhes' => [
                    'restricoes' => $restricoes,
                    'uso_atual' => $detalhes,
                    'sugestao' => 'Reduza o uso atual ou escolha um plano com limites maiores'
                ]
            ];
        }

        return [
            'permitido' => true,
            'tipo' => 'downgrade',
            'motivo' => 'Downgrade permitido: uso atual está dentro dos limites do novo plano',
            'detalhes' => [
                'uso_atual' => $detalhes
            ]
        ];
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


    public function atualizarStatus(Request $request): JsonResponse
    {
        $campos = ['idUsuario'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::MissingRequiredFields->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::findOrFail($request->idUsuario);

        if (isset($usuario->id)) {
            $currentStatus = $usuario->status;
            $usuario->status = $currentStatus === 1 ? 0 : 1;
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
 /*

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

        // Verifica se usuário existe e senha está correta
    if (!$user || !Hash::check($request->senha, $user->senha)) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Unauthorized->value,
                'message' => HttpCodesEnum::InvalidLogin->description()
            ], 401);
        }

        // Verifica status do usuário
        if ($user->status === 0) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => HttpCodesEnum::UserBlockedDueToInactivity->description()
            ], 400);
        }

        // Verifica plano pago e dataLimiteCompra
        $plano = Planos::find($user->idPlano);
        $isPlanoPago = $plano && $plano->valor > 0;
        if ($isPlanoPago && Helper::checkDateIsPassed($user->dataLimiteCompra)) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::PaymentRequired->value ?? 402,
                'message' => HttpCodesEnum::ExpiredSubscription->description()
            ], 402);
        }

        $currentMonth = now()->month;
        $currentYear = now()->year;
        $limitesInfo = $this->calcularLimitesUsuario($user, $plano, $currentMonth, $currentYear);

        // Recupera as pastas estruturadas
    $pastas = $this->getPastasEstruturadas($user->id);

        // Atualiza último acesso
        $user->ultimoAcesso = now();
        $user->save();

        $token = JWTAuth::fromUser($user);

        // Buscar tags associadas ao usuário
        $tags = Tag::where(function ($query) use ($user) {
            $query->where('idUsuarioCriador', $user->id)
                ->where('status', Helper::ATIVO);
        })->orWhere(function ($query) {
            $query->whereHas('usuario', function ($q) {
                $q->where('idPerfil', Helper::ID_PERFIL_ADMIN);
            })->where('status', Helper::ATIVO);
        })->select(['id', 'nomeTag', 'idUsuarioCriador', 'created_at'])
            ->orderBy('nomeTag', 'asc')
            ->get()
            ->map(function ($tag) use ($user) {
                return [
                    'id' => $tag->id,
                    'nome' => $tag->nomeTag,
                    'tipo' => $tag->idUsuarioCriador == $user->id ? 'pessoal' : 'global',
                    'criada_em' => $tag->created_at->format('Y-m-d H:i:s')
                ];
            });

        $dadosUsuario = $user->toArray();
        unset($dadosUsuario['pastas']);
        $dadosUsuario['pastas'] = $pastas;
        $dadosUsuario['tags'] = [
            'total' => $tags->count(),
            'pessoais' => $tags->where('tipo', 'pessoal')->count(),
            'globais' => $tags->where('tipo', 'global')->count(),
            'lista' => $tags->values()
        ];
        $dadosUsuario['regras'] = $limitesInfo['resumo'];

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'token' => $token,
            'dados' => $dadosUsuario,
            'pastas' => $pastas
        ]);
    }

    /**
     * Autentica um usuário administrador
     *
     * Semelhante ao método `autenticar`, porém exige que o usuário tenha idPerfil == 2
     * (administrador). Retorna token JWT e dados do usuário ao sucesso.
     *
     * @param AutenticarUsuarioRequest $request
     * @return JsonResponse
     */
    public function autenticarAdmin(AutenticarUsuarioRequest $request): JsonResponse
    {
        $user = Usuarios::with(['pastas.photos'])->where('cpf', $request->cpf)->first();

        // Verifica se usuário existe e senha está correta
        if (!$user || !Hash::check($request->senha, $user->senha)) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Unauthorized->value,
                'message' => HttpCodesEnum::InvalidLogin->description()
            ], 401);
        }

        if (($user->idPerfil ?? null) != Helper::ID_PERFIL_ADMIN) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Forbidden->value,
                'message' => 'Acesso negado: usuário não é administrador.'
            ], 403);
        }

       

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'dados' => $user->toArray(),
        ]);
    }

    /**
     * Cadastra um novo usuário no sistema
     * 
     * Valida dados pessoais, cria conta do usuário, envia email de boas-vindas
     * e processa convites pendentes se existirem.
     *
     * Exemplo de request:
     * {
     *   "primeiroNome": "João",
     *   "sobrenome": "Silva",
     *   "apelido": "jsilva",
     *   "cpf": "12345678901",
     *   "senha": "SenhaForte@123",
     *   "telefone": "11999999999",
     *   "email": "joao.silva@email.com",
     *   "idPlano": 1,
     *   "nascimento": "01/01/1990"
     * }
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
            if(!$this->validaSenha($request->senha)) {
                return $this->respostaErro(HttpCodesEnum::BadRequest, [
                    'message' => HttpCodesEnum::InvalidPassword->description()
                ]);
            }
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
        // Só define perfil/plano de convidado se o usuário ainda não tiver um plano/perfil definido
        if (empty($usuario->idPerfil) || $usuario->idPerfil == Helper::ID_PERFIL_CONVIDADO) {
            $usuario->idPerfil = Helper::ID_PERFIL_CONVIDADO;
        }
        if (empty($usuario->idPlano) || $usuario->idPlano == Helper::ID_PLANO_CONVIDADO) {
            $usuario->idPlano = Helper::ID_PLANO_CONVIDADO;
        }
        $usuario->save();

        $pasta = Pastas::findOrFail($convite->idPasta);
        // Garante associação direta na tabela de relacionamento
        if (!$pasta->usuario()->where('usuario_id', $usuario->id)->exists()) {
            $pasta->usuario()->attach($usuario->id);
        }
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

        // Buscar dados do plano do usuário
        $plano = Planos::find($user->idPlano);
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Calcular limites de criação para o mês atual
        $limitesInfo = $this->calcularLimitesUsuario($user, $plano, $currentMonth, $currentYear);

        // Buscar todas as pastas do usuário com relacionamentos
        $todasPastas = Pastas::where('idUsuario', $user->id)
            ->with(['photos', 'subpastas.photos'])
            ->get();

        // Separar pastas principais das subpastas
        $pastasPrincipais = $todasPastas->whereNull('idPastaPai');

        // Buscar tags associadas ao usuário
        $tags = Tag::where(function ($query) use ($user) {
            // Tags criadas pelo próprio usuário
            $query->where('idUsuarioCriador', $user->id)
                ->where('status', Helper::ATIVO);
        })->orWhere(function ($query) {
            // Tags criadas por administradores (públicas/globais)
            $query->whereHas('usuario', function ($q) {
                $q->where('idPerfil', Helper::ID_PERFIL_ADMIN);
            })->where('status', Helper::ATIVO);
        })->select(['id', 'nomeTag', 'idUsuarioCriador', 'created_at'])
            ->orderBy('nomeTag', 'asc')
            ->get()
            ->map(function ($tag) use ($user) {
                return [
                    'id' => $tag->id,
                    'nome' => $tag->nomeTag,
                    'tipo' => $tag->idUsuarioCriador == $user->id ? 'pessoal' : 'global',
                    'criada_em' => $tag->created_at->format('Y-m-d H:i:s')
                ];
            });

        // Criar estrutura completa das pastas com caminhos
        $pastas = $pastasPrincipais->map(function ($pasta) use ($limitesInfo) {
            // Buscar subpastas desta pasta principal
            $subpastas = Pastas::where('idPastaPai', $pasta->id)
                ->with('photos')
                ->get()
                ->map(function ($subpasta) use ($pasta) {
                    return [
                        'id' => $subpasta->id,
                        'nome' => $subpasta->nome,
                        'path' => Helper::formatFolderUrl($subpasta),
                        'idPastaPai' => $subpasta->idPastaPai,
                        'imagens' => $subpasta->photos->map(fn($photo) => [
                            'id' => $photo->id,
                            'path' => Helper::formatImageUrl($photo->path),
                            'taken_at' => $photo->taken_at
                        ])->values()
                    ];
                })->values();

            return [
                'nome' => $pasta->nome,
                'id' => $pasta->id,
                'path' => Helper::formatFolderUrl($pasta),
                'idPastaPai' => null,
                'subpastas' => $subpastas

            ];
        })->values();

        // Lista plana de todas as pastas e subpastas com caminhos completos
        $todasPastasComCaminho = [];

        foreach ($pastasPrincipais as $pastaPrincipal) {
            // Adicionar pasta principal
            $todasPastasComCaminho[] = [
                'id' => $pastaPrincipal->id,
                'nome' => $pastaPrincipal->nome,
                'tipo' => 'pasta_principal',
                'caminho_completo' => $pastaPrincipal->nome,
                'idPastaPai' => null,
                'nivel' => 1,
                'created_at' => $pastaPrincipal->created_at,
                'total_imagens' => $pastaPrincipal->photos->count()
            ];

            // Adicionar subpastas
            $subpastas = Pastas::where('idPastaPai', $pastaPrincipal->id)->with('photos')->get();
            foreach ($subpastas as $subpasta) {
                $todasPastasComCaminho[] = [
                    'id' => $subpasta->id,
                    'nome' => $subpasta->nome,
                    'tipo' => 'subpasta',
                    'caminho_completo' => $pastaPrincipal->nome . '/' . $subpasta->nome,
                    'idPastaPai' => $subpasta->idPastaPai,
                    'pasta_pai_nome' => $pastaPrincipal->nome,
                    'nivel' => 2,
                    'created_at' => $subpasta->created_at,
                    'total_imagens' => $subpasta->photos->count()
                ];
            }
        }

        // Atualiza último acesso
        $user->ultimoAcesso = now();
        $user->save();

        // Converte o usuário para array e remove a relação 'pastas'
        $dadosUsuario = $user->toArray();
        unset($dadosUsuario['pastas']);
        $dadosUsuario['pastas'] = $pastas; // Estrutura hierárquica
        $dadosUsuario['tags'] = [
            'total' => $tags->count(),
            'pessoais' => $tags->where('tipo', 'pessoal')->count(),
            'globais' => $tags->where('tipo', 'global')->count(),
            'lista' => $tags->values()
        ];
        $dadosUsuario['regras'] = $limitesInfo['resumo'];

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'token' => $token,
            'dados' => $dadosUsuario,
            'pastas' => $pastas

        ]);
    }
    /**
     * Calcula os limites de criação de pastas e subpastas para o usuário
     */
    private function calcularLimitesUsuario(Usuarios $user, Planos $plano, int $currentMonth, int $currentYear): array
    {
        // Contar pastas principais criadas no mês
        $pastasPrincipaisCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->whereNull('idPastaPai')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        $pastasPrincipaisRestantes = max(0, $plano->quantidadePastas - $pastasPrincipaisCriadasNoMes);

        // Para cada pasta principal, calcular quantas subpastas podem ser criadas
        $pastasUsuario = Pastas::where('idUsuario', $user->id)
            ->whereNull('idPastaPai')
            ->get();

        $subpastasPorPasta = [];

        foreach ($pastasUsuario as $pasta) {
            $subpastasCriadasNoMes = Pastas::where('idUsuario', $user->id)
                ->where('idPastaPai', $pasta->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->count();

            $subpastasRestantes = max(0, $plano->quantidadeSubpastas - $subpastasCriadasNoMes);

            $subpastasPorPasta[$pasta->id] = [
                'pasta_nome' => $pasta->nome,
                'criadas_no_mes' => $subpastasCriadasNoMes,
                'limite_plano' => $plano->quantidadeSubpastas,
                'restantes' => $subpastasRestantes,
                'pode_criar' => $subpastasCriadasNoMes < $plano->quantidadeSubpastas
            ];
        }

        // Verifica se há pelo menos uma pasta onde pode criar subpasta
        $podeCriarSubpastas = collect($subpastasPorPasta)->contains(function ($info) {
            return $info['pode_criar'] === true;
        });

        return [
            'resumo' => [
                'pode_criar_nova_pasta' => $pastasPrincipaisRestantes > 0,
                'pode_criar_subpastas' => $podeCriarSubpastas,
            ],
            'subpastas_por_pasta' => $subpastasPorPasta,
        ];
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
        $query = Usuarios::with('plano')->orderBy('status', 'desc')->orderBy('primeiroNome', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('nome')) {
            $query->where('primeiroNome', 'like', '%' . $request->input('nome') . '%');
        }

        $usuarios = $query->get()->map(function($usuario) {
            $usuarioArray = $usuario->toArray();
            $usuarioArray['plano'] = Planos::find($usuario->idPlano)->nome ?? null;
            // Formata todas as datas para o padrão brasileiro
            foreach ($usuarioArray as $key => $value) {
                if ((strpos($key, 'data') !== false || strpos($key, 'created_at') !== false || strpos($key, 'updated_at') !== false || $key === 'ultimoAcesso') && !empty($value)) {
                    try {
                        $date = \Carbon\Carbon::parse($value);
                        $usuarioArray[$key] = $date->format('d/m/Y H:i');
                    } catch (\Exception $e) {
                        // Se não for data válida, mantém o valor original
                    }
                }
            }
            $usuarioArray['cadastrado_em'] = $usuario->created_at ? $usuario->created_at->format('d/m/Y H:i') : null;
            $usuarioArray['status'] = $usuario->status ? 'Ativo' : 'Inativo';

            return $usuarioArray;
        });

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
        // Se o array $extras tiver uma chave 'message', use ela, senão use a descrição padrão
        $mensagem = $extras['message'] ?? $codigo->description();
        $resposta = [
            'codRetorno' => $codigo->value,
            'message' => $mensagem
        ];
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
    public function validaExistenciaUsuario(Request $request): JsonResponse
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
