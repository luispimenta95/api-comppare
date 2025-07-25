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
    
    // Criar estrutura completa das pastas com caminhos
    $pastas = $pastasPrincipais->map(function($pasta) use ($limitesInfo) {
        // Buscar subpastas desta pasta principal
        $subpastas = Pastas::where('idPastaPai', $pasta->id)
            ->with('photos')
            ->get()
            ->map(function($subpasta) use ($pasta) {
                return [
                    'id' => $subpasta->id,
                    'nome' => $subpasta->nome,
                    'caminho' => $pasta->nome . '/' . $subpasta->nome, // Caminho completo
                    'caminho_completo' => $pasta->caminho . '/' . $subpasta->nome,
                    'idPastaPai' => $subpasta->idPastaPai,
                    'created_at' => $subpasta->created_at,
                    'imagens' => $subpasta->photos->map(fn($photo) => [
                        'id' => $photo->id,
                        'path' => $photo->path,
                        'taken_at' => $photo->taken_at
                    ])->values()
                ];
            })->values();

        return [
            'id' => $pasta->id,
            'nome' => $pasta->nome,
            'caminho' => $pasta->nome, // Nome da pasta principal
            'caminho_completo' => $pasta->caminho ?? $pasta->nome,
            'idPastaPai' => null,
            'created_at' => $pasta->created_at,
            'imagens' => $pasta->photos->map(fn($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'taken_at' => $photo->taken_at
            ])->values(),
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

    return response()->json([
        'codRetorno' => HttpCodesEnum::OK->value,
        'message' => HttpCodesEnum::OK->description(),
        'token' => $token,
        'dados' => $dadosUsuario,
        'pastas' => $pastas, // Estrutura hierárquica
        'regras' => $limitesInfo['resumo']
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
                'pode_criar' => $subpastasRestantes > 0
            ];
        }

        return [
            'resumo' => [
                    'pode_criar_nova_pasta' => $pastasPrincipaisRestantes > 0,
                    'pode_criar_subpastas' => isset($limitesInfo['subpastas_por_pasta'][$pasta->id]['pode_criar_nova_pasta']) ? $limitesInfo['subpastas_por_pasta'][$pasta->id]['pode_criar_nova_pasta'] : false
            ],
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
