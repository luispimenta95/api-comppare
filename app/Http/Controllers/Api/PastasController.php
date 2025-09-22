<?php

namespace App\Http\Controllers\Api;

use App\Enums\HttpCodesEnum;
use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use App\Models\Pastas;
use App\Models\Photos;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\ComparacaoImagem;
use Illuminate\Support\Facades\Log;

class PastasController extends Controller
{

    /**
     * Lista todas as pastas (não implementado)
     * 
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Cria uma nova pasta para o usuário
     * 
     * Valida se o usuário existe e se não excedeu o limite mensal de pastas do seu plano.
     * Agora diferencia entre pastas principais e subpastas baseado na estrutura do nome.
     * 
     * Formato de entrada esperado:
     * - Pasta principal: nomePasta = "MinhasPastaPrincipal"
     * - Subpasta: nomePasta = "PastaPai/MinhaSubpasta"
     * 
     * Exemplos de request:
     * POST /api/pastas/create
     * Content-Type: application/json
     * 
     * Para pasta principal:
     * {
     *   "idUsuario": 1,
     *   "nomePasta": "Viagem2024"
     * }
     * 
     * Para subpasta:
     * {
     *   "idUsuario": 1,
     *   "nomePasta": "Viagem2024/Praia"
     * }
     * 
     * @param Request $request - Deve conter: idUsuario, nomePasta (pode conter '/' para subpastas)
     * @return JsonResponse - Retorna o ID da pasta criada ou erro
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id',
            'nomePasta' => 'required|string|max:255',
        ]);

        $user = Usuarios::find($request->idUsuario);

        if (!$user) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::UserNotFound->description(),
            ]);
        }

        $plano = Planos::find($user->idPlano);
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Analisar a estrutura do nome da pasta para determinar se é principal ou subpasta
        $analiseEstrutura = $this->analisarEstruturaPasta($request->nomePasta, $user);

        if (!$analiseEstrutura['valido']) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $analiseEstrutura['erro'],
            ], 400);
        }

        $isPastaSubpasta = $analiseEstrutura['subfloder'];

        if ($isPastaSubpasta) {
            // Verificação para subpasta
            $verificacao = $this->verificarLimiteSubpastas($user, $analiseEstrutura['pasta_pai_id'], $plano, $currentMonth, $currentYear);
        } else {
            // Verificação para pasta principal
            $verificacao = $this->verificarLimitePastasPrincipais($user, $plano, $currentMonth, $currentYear);
        }

        if (!$verificacao['permitido']) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $verificacao['motivo'],
                'limite_atingido' => true,
                'detalhes' => $verificacao['detalhes']
            ], 400);
        }

        // Criar a pasta
        $resultado = $this->criarPasta($user, $analiseEstrutura, $isPastaSubpasta);

        return response()->json($resultado);
    }

    /**
     * Analisa a estrutura do nome da pasta para determinar se é principal ou subpasta
     */
    private function analisarEstruturaPasta(string $nomePasta, Usuarios $user): array
    {
        // Se contém '/', é uma tentativa de criar subpasta
        if (str_contains($nomePasta, '/')) {
            $partes = explode('/', $nomePasta);

            if (count($partes) != 2) {
                return [
                    'valido' => false,
                    'erro' => 'Formato inválido. Use "PastaPai/Subpasta" para criar subpastas.'
                ];
            }

            $nomePastaPai = trim($partes[0]);
            $nomeSubpasta = trim($partes[1]);

            if (empty($nomePastaPai) || empty($nomeSubpasta)) {
                return [
                    'valido' => false,
                    'erro' => 'Nome da pasta pai e subpasta não podem estar vazios.'
                ];
            }

            // Buscar a pasta pai pelo nome original (sem sanitização)
            $pastaPai = Pastas::where('idUsuario', $user->id)
                ->where('nome', $nomePastaPai)
                ->whereNull('idPastaPai') // Garantir que é uma pasta principal
                ->first();

            // Se não encontrou, tentar buscar pelo nome sanitizado (para compatibilidade com pastas antigas)
            if (!$pastaPai) {
                $nomePastaPaiSanitizado = $this->sanitizeFolderName($nomePastaPai);
                $pastaPai = Pastas::where('idUsuario', $user->id)
                    ->where('nome', $nomePastaPaiSanitizado)
                    ->whereNull('idPastaPai')
                    ->first();
            }

            // Se ainda não encontrou, tentar buscar considerando que o nome fornecido pode ser sanitizado
            // e a pasta no banco pode estar com o nome original
            if (!$pastaPai) {
                // Buscar todas as pastas principais do usuário e verificar se alguma, quando sanitizada, corresponde ao nome fornecido
                $todasPastasPrincipais = Pastas::where('idUsuario', $user->id)
                    ->whereNull('idPastaPai')
                    ->get();

                foreach ($todasPastasPrincipais as $pasta) {
                    $nomeSanitizado = $this->sanitizeFolderName($pasta->nome);
                    if ($nomeSanitizado === $nomePastaPai || $nomeSanitizado === $nomePastaPaiSanitizado) {
                        $pastaPai = $pasta;
                        break;
                    }
                }
            }

            if (!$pastaPai) {
                $pastasDisponiveis = $this->listarPastasDisponiveisDetalhado($user);
                return [
                    'valido' => false,
                    'erro' => "Pasta pai '{$nomePastaPai}' não encontrada. Certifique-se de que ela existe e pertence a você.",
                    'detalhes_busca' => [
                        'nome_procurado' => $nomePastaPai,
                        'nome_sanitizado_procurado' => $nomePastaPaiSanitizado ?? $this->sanitizeFolderName($nomePastaPai),
                        'pastas_disponiveis' => $pastasDisponiveis
                    ]
                ];
            }

            // Verificar se a subpasta já existe (verificar pelo nome original, não sanitizado)
            $subpastaExistente = Pastas::where('idUsuario', $user->id)
                ->where('nome', $nomeSubpasta)
                ->where('idPastaPai', $pastaPai->id)
                ->exists();

            if ($subpastaExistente) {
                return [
                    'valido' => false,
                    'erro' => "Subpasta '{$nomeSubpasta}' já existe dentro de '{$nomePastaPai}'."
                ];
            }

            return [
                'valido' => true,
                'subfloder' => true,
                'pasta_pai_id' => $pastaPai->id,
                'pasta_pai_nome' => $nomePastaPai,
                'nome_subpasta' => $nomeSubpasta,
                'pasta_pai_caminho' => $pastaPai->caminho
            ];
        } else {
            // É uma pasta principal
            $nomePasta = trim($nomePasta);

            if (empty($nomePasta)) {
                return [
                    'valido' => false,
                    'erro' => 'Nome da pasta não pode estar vazio.'
                ];
            }

            // Verificar se pasta principal já existe (verificar pelo nome original, não sanitizado)
            $pastaExistente = Pastas::where('idUsuario', $user->id)
                ->where('nome', $nomePasta)
                ->whereNull('idPastaPai')
                ->exists();

            if ($pastaExistente) {
                return [
                    'valido' => false,
                    'erro' => "Pasta principal '{$nomePasta}' já existe."
                ];
            }

            return [
                'valido' => true,
                'subfloder' => false,
                'nome_pasta_principal' => $nomePasta
            ];
        }
    }

    /**
     * Verifica se o usuário pode criar uma nova pasta principal
     */
    private function verificarLimitePastasPrincipais(Usuarios $user, Planos $plano, int $currentMonth, int $currentYear): array
    {
        // Contar apenas pastas principais (sem idPastaPai) criadas no mês
        $pastasPrincipaisCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->whereNull('idPastaPai') // Apenas pastas principais
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        if ($pastasPrincipaisCriadasNoMes >= $plano->quantidadePastas) {
            return [
                'permitido' => false,
                'motivo' => 'Limite de pastas principais atingido para este mês',
                'detalhes' => [
                    'tipo' => 'pasta_principal',
                    'criadas_no_mes' => $pastasPrincipaisCriadasNoMes,
                    'limite_plano' => $plano->quantidadePastas,
                    'restantes' => 0
                ]
            ];
        }

        return [
            'permitido' => true,
            'detalhes' => [
                'tipo' => 'pasta_principal',
                'criadas_no_mes' => $pastasPrincipaisCriadasNoMes,
                'limite_plano' => $plano->quantidadePastas,
                'restantes' => $plano->quantidadePastas - $pastasPrincipaisCriadasNoMes
            ]
        ];
    }

    /**
     * Verifica se o usuário pode criar uma subpasta dentro de uma pasta específica
     */
    private function verificarLimiteSubpastas(Usuarios $user, int $idPastaPai, Planos $plano, int $currentMonth, int $currentYear): array
    {
        // Verificar se a pasta pai pertence ao usuário
        $pastaPai = Pastas::where('id', $idPastaPai)
            ->where('idUsuario', $user->id)
            ->first();

        if (!$pastaPai) {
            return [
                'permitido' => false,
                'motivo' => 'Pasta pai não encontrada ou não pertence ao usuário',
                'detalhes' => ['tipo' => 'pasta_pai_invalida']
            ];
        }

        // Contar subpastas da pasta pai criadas no mês
        $subpastasCriadasNoMes = Pastas::where('idUsuario', $user->id)
            ->where('idPastaPai', $idPastaPai)
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        if ($subpastasCriadasNoMes >= $plano->quantidadeSubpastas) {
            return [
                'permitido' => false,
                'motivo' => 'Limite de subpastas atingido para esta pasta neste mês',
                'detalhes' => [
                    'tipo' => 'subpasta',
                    'pasta_pai_id' => $idPastaPai,
                    'pasta_pai_nome' => $pastaPai->nome,
                    'criadas_no_mes' => $subpastasCriadasNoMes,
                    'limite_plano' => $plano->quantidadeSubpastas,
                    'restantes' => 0
                ]
            ];
        }

        return [
            'permitido' => true,
            'detalhes' => [
                'tipo' => 'subpasta',
                'pasta_pai_id' => $idPastaPai,
                'pasta_pai_nome' => $pastaPai->nome,
                'criadas_no_mes' => $subpastasCriadasNoMes,
                'limite_plano' => $plano->quantidadeSubpastas,
                'restantes' => $plano->quantidadeSubpastas - $subpastasCriadasNoMes
            ]
        ];
    }

    /**
     * Cria a pasta fisicamente e no banco de dados
     */
    private function criarPasta(Usuarios $user, array $analiseEstrutura, bool $isPastaSubpasta): array
    {
        try {
            if ($isPastaSubpasta) {
                // Para subpastas, criar dentro da pasta pai
                $nomeSubpastaSanitizado = $this->sanitizeFolderName($analiseEstrutura['nome_subpasta']);
                $folderName = $analiseEstrutura['pasta_pai_caminho'] . '/' . $nomeSubpastaSanitizado;
                $nomePastaParaBanco = $analiseEstrutura['nome_subpasta']; // Nome original para o banco
                $idPastaPai = $analiseEstrutura['pasta_pai_id'];
            } else {
                // Para pastas principais
                $nomeUsuarioSanitizado = $this->sanitizeFolderName($user->primeiroNome . '_' . $user->sobrenome);
                $nomePastaSanitizada = $this->sanitizeFolderName($analiseEstrutura['nome_pasta_principal']);
                $folderName = $nomeUsuarioSanitizado . '/' . $nomePastaSanitizada;
                $nomePastaParaBanco = $analiseEstrutura['nome_pasta_principal']; // Nome original para o banco
                $idPastaPai = null;
            }

            $folder = Helper::createFolder($folderName);

            if ($folder['path'] !== null) {
                // Criar registro no banco
                $novaPasta = Pastas::create([
                    'nome' => $nomePastaParaBanco,
                    'idUsuario' => $user->id,
                    'caminho' => $folder['path'],
                    'idPastaPai' => $idPastaPai
                ]);

                // Incrementar contador apropriado
                if (!$isPastaSubpasta) {
                    $user->increment('pastasCriadas');
                } else {
                    $user->increment('subpastasCriadas');
                }

                return [
                    'codRetorno' => HttpCodesEnum::Created->value,
                    'message' => $isPastaSubpasta ? 'Subpasta criada com sucesso!' : 'Pasta criada com sucesso!',
                    'pasta_id' => $novaPasta->id,
                    'pasta_nome' => $novaPasta->nome,
                    'pasta_caminho' => $novaPasta->caminho,
                    'tipo' => $isPastaSubpasta ? 'subpasta' : 'pasta_principal',
                    'estrutura_completa' => $isPastaSubpasta
                        ? $analiseEstrutura['pasta_pai_nome'] . '/' . $analiseEstrutura['nome_subpasta']
                        : $analiseEstrutura['nome_pasta_principal']
                ];
            } else {
                return [
                    'codRetorno' => HttpCodesEnum::InternalServerError->value,
                    'message' => 'Erro ao criar a pasta no sistema de arquivos'
                ];
            }
        } catch (\Exception $e) {
            return [
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => 'Erro interno ao criar a pasta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Armazena um novo recurso (não implementado)
     * 
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Exibe uma pasta específica (não implementado)
     * 
     * @param Pastas $pastas
     * @return void
     */
    public function show(Pastas $pastas)
    {
        //
    }

    /**
     * Exibe formulário para editar uma pasta (não implementado)
     * 
     * @param Pastas $pastas
     * @return void
     */
    public function edit(Request $request): JsonResponse
    {
        $request->validate([
            'idUsuario' => 'required|exists:usuarios,id',
            'idPasta' => 'required|exists:pastas,id',
            'novoNome' => 'required|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id'
        ]);

        $user = Usuarios::find($request->idUsuario);
        $pasta = Pastas::find($request->idPasta);

        if (!$user) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::UserNotFound->description(),
            ]);
        }

        if (!$pasta) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Pasta não encontrada.',
            ]);
        }

        if ($pasta->idUsuario !== $user->id) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::Forbidden->value,
                'message' => 'Você não tem permissão para editar esta pasta.',
            ]);
        }

        $novoNomeSanitizado = $this->sanitizeFolderName($request->novoNome);

        // Caminho antigo e novo
        $caminhoAntigo = $pasta->caminho;
        $caminhoBase = dirname($caminhoAntigo);
        $caminhoNovo = $caminhoBase . '/' . $novoNomeSanitizado;

        // Move o diretório físico
        $publicPath = env('PUBLIC_PATH', storage_path('app/public/'));
        $caminhoAntigoRelativo = str_replace($publicPath, '', $caminhoAntigo);
        $caminhoNovoRelativo = str_replace($publicPath, '', $caminhoNovo);

        if (Storage::disk('public')->exists($caminhoAntigoRelativo)) {
            Storage::disk('public')->move($caminhoAntigoRelativo, $caminhoNovoRelativo);
        } else {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Diretório físico da pasta não encontrado.',
            ]);
        }

        // Atualiza o nome e caminho no banco
        $pasta->nome = $request->novoNome;
        $pasta->caminho = $caminhoNovo;
        $pasta->save();

        // Atualiza as tags
        if (isset($request->tags)) {
            $pasta->tags()->sync($request->tags);
        }

        // Atualiza subpastas (se for pasta principal)
        if (is_null($pasta->idPastaPai)) {
            $subpastas = Pastas::where('idPastaPai', $pasta->id)->get();
            foreach ($subpastas as $subpasta) {
                $subCaminhoAntigo = $subpasta->caminho;
                $subNovoCaminho = str_replace($caminhoAntigo, $caminhoNovo, $subCaminhoAntigo);

                $subAntigoRelativo = str_replace($publicPath, '', $subCaminhoAntigo);
                $subNovoRelativo = str_replace($publicPath, '', $subNovoCaminho);

                if (Storage::disk('public')->exists($subAntigoRelativo)) {
                    Storage::disk('public')->move($subAntigoRelativo, $subNovoRelativo);
                }
                $subpasta->caminho = $subNovoCaminho;
                $subpasta->save();
            }
        }

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Nome da pasta atualizado com sucesso!',
            'pasta_id' => $pasta->id,
            'novo_nome' => $pasta->nome,
            'novo_caminho' => $pasta->caminho,
        ]);
    }

    /**
     * Atualiza uma pasta específica (não implementado)
     * 
     * @param Request $request
     * @param Pastas $pastas
     * @return void
     */
    public function update(Request $request, Pastas $pastas)
    {
        //
    }

    /**
     * Remove uma pasta do storage e banco de dados
     * 
     * Valida os dados de entrada, verifica se a pasta existe e pertence ao usuário,
     * deleta todas as fotos associadas, remove a pasta física do storage,
     * exclui o registro do banco de dados e decrementa o contador de pastas do usuário.
     * 
     * Exemplo de request:
     * DELETE /api/pasta/delete
     * Content-Type: application/json
     * 
     * Body:
     * {
     *   "idUsuario": 1,
     *   "idPasta": 15
     * }
     * 
     * @param Request $request - Deve conter: idUsuario, idPasta
     * @return JsonResponse - Confirmação da exclusão ou erro
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'idUsuario' => 'required|exists:usuarios,id',
                'idPasta' => 'required|exists:pastas,id',
            ]);

            $user = Usuarios::find($request->idUsuario);
            $pasta = Pastas::find($request->idPasta);

            // Verifica se o usuário foi encontrado
            if (!$user) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => HttpCodesEnum::UserNotFound->description(),
                ]);
            }

            // Verifica se a pasta foi encontrada
            if (!$pasta) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => 'Pasta não encontrada.',
                ]);
            }

            // Verifica se a pasta pertence ao usuário
            if ($pasta->idUsuario !== $user->id) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::Forbidden->value,
                    'message' => 'Você não tem permissão para excluir esta pasta.',
                ]);
            }

            // Determinar se é pasta principal ou subpasta
            $isPastaPrincipal = is_null($pasta->idPastaPai);
            $isSubpasta = !is_null($pasta->idPastaPai);

            // Salvar informações antes de deletar
            $nomePasta = $pasta->nome;
            $tipoPasta = $isPastaPrincipal ? 'pasta_principal' : 'subpasta';
            $pastaPaiInfo = null;
            $subpastasCount = 0;

            if ($isSubpasta) {
                $pastaPai = Pastas::find($pasta->idPastaPai);
                $pastaPaiInfo = $pastaPai ? $pastaPai->nome : 'Pasta pai não encontrada';
            }

            // REGRA 1: Se for pasta principal, excluir TODAS as subpastas primeiro
            if ($isPastaPrincipal) {
                $subpastas = Pastas::where('idPastaPai', $pasta->id)->get();
                $subpastasCount = $subpastas->count();

                foreach ($subpastas as $subpasta) {
                    // Excluir fotos da subpasta e suas comparações
                    $subpastaPhotos = Photos::where('pasta_id', $subpasta->id)->get();
                    foreach ($subpastaPhotos as $photo) {
                        ComparacaoImagem::where('id_photo', $photo->id)->delete();
                        $photo->delete();
                    }

                    // Excluir pasta física da subpasta
                    $subpastaRelativePath = str_replace(
                        env('PUBLIC_PATH', '/home/u757410616/domains/comppare.com.br/public_html/api-comppare/storage/app/public/'),
                        '',
                        $subpasta->caminho
                    );
                    $subpastaRelativePath = trim($subpastaRelativePath, '/');

                    if (Storage::disk('public')->exists($subpastaRelativePath)) {
                        Storage::disk('public')->deleteDirectory($subpastaRelativePath);
                    }

                    // Remover associações da subpasta
                    $subpasta->usuario()->detach();

                    // Excluir registro da subpasta
                    $subpasta->delete();
                }

                // Atualizar contador de subpastas (todas as subpastas da pasta principal foram removidas)
                if ($user->subpastasCriadas >= $subpastasCount) {
                    $user->decrement('subpastasCriadas', $subpastasCount);
                } else {
                    $user->update(['subpastasCriadas' => 0]);
                }
            }

            // Remove todas as fotos associadas à pasta principal da tabela photos e suas comparações
            $photos = Photos::where('pasta_id', $pasta->id)->get();
            $photosCount = $photos->count();
            foreach ($photos as $photo) {
                \App\Models\ComparacaoImagem::where('id_photo', $photo->id)->delete();
                $photo->delete();
            }

            // Remove a pasta física do storage
            $relativePath = str_replace(
                env('PUBLIC_PATH', '/home/u757410616/domains/comppare.com.br/public_html/api-comppare/storage/app/public/'),
                '',
                $pasta->caminho
            );
            $relativePath = trim($relativePath, '/');

            // Deleta a pasta física e todo seu conteúdo
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->deleteDirectory($relativePath);
            }

            // Remove associações da pasta com usuários (pivot table pasta_usuario)
            $pasta->usuario()->detach();

            // Remove o registro da pasta do banco de dados
            $pasta->delete();

            // IMPORTANTE: Atualizar contadores apropriados baseado nas regras
            if ($isPastaPrincipal) {
                // REGRA 1: Pasta principal - decrementa apenas o contador de pastas principais
                // (subpastas já foram decrementadas acima)
                if ($user->pastasCriadas > 0) {
                    $user->decrement('pastasCriadas');
                }
            } else {
                // REGRA 2: Subpasta - decrementa apenas o contador de subpastas
                if ($user->subpastasCriadas > 0) {
                    $user->decrement('subpastasCriadas');
                }
            }

            // Preparar resposta detalhada baseada nas regras implementadas
            $detalhes = [
                'pasta_excluida' => $nomePasta,
                'tipo' => $tipoPasta,
                'fotos_removidas' => $photosCount,
                'pastas_principais_restantes' => $user->fresh()->pastasCriadas,
                'subpastas_restantes' => $user->fresh()->subpastasCriadas
            ];

            if ($isPastaPrincipal) {
                if ($subpastasCount > 0) {
                    $detalhes['subpastas_excluidas'] = $subpastasCount;
                    $detalhes['observacao'] = "REGRA 1 APLICADA: Pasta principal excluída junto com {$subpastasCount} subpasta(s)";
                } else {
                    $detalhes['observacao'] = "REGRA 1 APLICADA: Pasta principal excluída (sem subpastas)";
                }
            } else {
                $detalhes['pasta_pai'] = $pastaPaiInfo;
                $detalhes['observacao'] = 'REGRA 2 APLICADA: Subpasta excluída (pasta pai mantida)';
            }

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => $isPastaPrincipal
                    ? "Pasta principal '{$nomePasta}' excluída com sucesso!"
                    : "Subpasta '{$nomePasta}' excluída com sucesso!",
                'detalhes' => $detalhes
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao excluir pasta: {$e->getMessage()}");
            return response()->json([
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => 'Erro interno do servidor ao excluir a pasta.',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Salva imagem(ns) em uma pasta específica
     * 
     * Valida e processa upload de uma ou múltiplas imagens para uma pasta específica.
     * Cria registros na tabela photos para cada imagem carregada com sucesso.
     * Suporta data personalizada para quando a foto foi tirada (formato brasileiro).
     * 
     * Exemplo de request com todos os campos preenchidos:
     * POST /api/pastas/save-image
     * Content-Type: multipart/form-data
     * 
     * Body:
     * - image: [arquivo de imagem] (obrigatório) - pode ser um arquivo único ou array de arquivos
     * - image[]: [arquivo de imagem adicional] (opcional) - para múltiplas imagens
     * - idPasta: 15 (obrigatório) - ID da pasta onde salvar as imagens
     * - dataFoto: "25/12/2024" (opcional) - data em que a foto foi tirada no formato brasileiro
     * 
     * Formatos de imagem aceitos: jpeg, png, jpg, gif, svg
     * Se dataFoto não for fornecida, será usada a data/hora atual
     * Formato de data aceito: d/m/Y (exemplo: 25/12/2024)
     * 
     * @param Request $request - Dados da requisição
     * @return JsonResponse - URLs das imagens carregadas ou erro
     */
    public function saveImageInFolder(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required',
                'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
                'idPasta' => 'required|exists:pastas,id',
                'dataFoto' => 'nullable|string',
            ]);

            $pasta = Pastas::find($request->idPasta);            // Define a data para o campo taken_at - aceita formato brasileiro
            $takenAt = now(); // valor padrão

            if ($request->has('dataFoto') && $request->dataFoto) {
                try {
                    // Converte data no formato brasileiro (d/m/Y) para Carbon
                    $takenAt = \Carbon\Carbon::createFromFormat('d/m/Y', $request->dataFoto);
                } catch (\Exception $e) {
                    // Se falhar, mantém a data atual
                    $takenAt = now();
                }
            }

            // Remove tudo antes de "storage/app/public/"
            $relativePath =  str_replace(
                env('PUBLIC_PATH', '/home/u757410616/domains/comppare.com.br/public_html/api-comppare/storage/app/public/'),

                '',
                $pasta->caminho
            );
            $relativePath = trim($relativePath, '/');

            $uploadedImages = [];

            $images = is_array($request->file('image'))
                ? $request->file('image')
                : [$request->file('image')];

            foreach ($images as $image) {
                if ($image && $image->isValid()) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $path = $image->storeAs($relativePath, $imageName, 'public');
                    $imageUrl = env('APP_URL') . Storage::url($path);
                    $uploadedImages[] = $imageUrl;

                    // Criar registro na tabela Photos
                    Photos::create([
                        'pasta_id' => $pasta->id,
                        'path' => $imageUrl,
                        'taken_at' => $takenAt,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            if (empty($uploadedImages)) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'Nenhuma imagem válida foi enviada.',
                ]);
            }

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Imagem(ns) carregada(s) com sucesso!',
                'image_paths' => $uploadedImages,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => 'Validação falhou. Dados fornecidos inválidos.',
            ]);
        }
    }


    /**
     * Sincroniza tags de uma pasta
     * 
     * Remove todas as tags atuais da pasta e associa as novas tags fornecidas.
     * Operação de substituição completa das tags existentes.
     * 
     * @param Request $request - Deve conter: folder (ID da pasta), tags (array de IDs das tags)
     * @return JsonResponse - Confirmação da operação e lista das tags associadas
     */
    public function syncTagsToFolder(Request $request)
    {
        $request->validate([
            'pasta' => 'required|exists:pastas,id',
            'tags' => 'required|array',
            'tags.*' => 'required|integer|exists:tags,id',
        ]);

        $folder = Pastas::findOrFail($request->pasta);

        // Atualiza as tags da pasta, removendo as antigas
        $folder->tags()->sync($request->tags);

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Tags associadas com sucesso!',
            'id_pasta' => $folder->id,
            'tags' => $request->tags,
        ]);
    }

    /**
     * Remove uma tag específica de uma pasta
     * 
     * Desassocia uma tag específica de uma pasta sem afetar as outras tags.
     * Operação de remoção individual de tag.
     * 
     * @param Request $request - Deve conter: folder_id (ID da pasta), tag_id (ID da tag)
     * @return JsonResponse - Confirmação da remoção da tag
     */
    public function detachTagFromFolder(Request $request)
    {
        $request->validate([
            'folder_id' => 'required|exists:pastas,id',
            'tag_id' => 'required|exists:tags,id',
        ]);

        $folder = Pastas::findOrFail($request->folder_id);

        // Remove a tag da pasta
        $folder->tags()->detach($request->tag_id);

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => 'Tag removida da pasta com sucesso!',
            'folder_id' => $folder->id,
            'tag_id' => $request->tag_id,
        ]);
    }
    /**
     * Busca todas as pastas de um usuário específico
     * 
     * Valida se o usuário existe e retorna todas as pastas associadas a ele,
     * incluindo a hierarquia de subpastas com caminhos formatados.
     * 
     * @param Request $request - Deve conter: idUsuario (ID do usuário)
     * @return JsonResponse - Lista de pastas do usuário ou erro se usuário não encontrado
     */
    public function getFoldersByUser(int $id): JsonResponse         
    {

        $user = Usuarios::find($id);

        // Verifica se o usuário foi encontrado
        if (!$user) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::UserNotFound->description(),
            ]);
        }

        // Busca as pastas do usuário com hierarquia e fotos
        $pastas = Pastas::with(['photos', 'subpastas.photos'])
            ->where('idUsuario', $user->id)
            ->whereNull('idPastaPai') // Apenas pastas principais
            ->get();

        $pastasFormatadas = $pastas->map(function ($pasta) {
            return [
                'id' => $pasta->id,
                'nome' => $pasta->nome,
                'caminho' => Helper::formatFolderUrl($pasta),
                'subpastas' => $pasta->subpastas->map(function ($subpasta) {
                    return [
                        'id' => $subpasta->id,
                        'nome' => $subpasta->nome,
                        'caminho' => Helper::formatFolderUrl($subpasta),
                        'imagens' => $subpasta->photos->map(function ($photo) {
                            return [
                                'id' => $photo->id,
                                'path' => Helper::formatImageUrl($photo->path), // URL clicável
                               'taken_at' => $photo->taken_at ? $photo->taken_at->format('d/m/Y') : null, 
                            ];
                        })->values()->toArray()
                    ];
                })->values()
            ];
        });

        return response()->json([
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'pastas' => $pastasFormatadas,
        ]);
    }

    /**
     * Exclui uma imagem específica de uma pasta
     * 
     * Remove uma imagem individual do storage e da tabela photos.
     * Verifica se a imagem pertence ao usuário antes de excluir.
     * 
     * Exemplo de request:
     * DELETE /api/imagens/excluir
     * Content-Type: application/json
     * 
     * Body:
     * {
     *   "idUsuario": 1,
     *   "idImagem": 25
     * }
     * 
     * @param Request $request - Deve conter: idUsuario, idImagem
     * @return JsonResponse - Confirmação da exclusão ou erro
     */
    public function deleteImageFromFolder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'idUsuario' => 'required|exists:usuarios,id',
                'idImagem' => 'required|exists:photos,id',
            ]);

            $user = Usuarios::find($request->idUsuario);
            $photo = Photos::find($request->idImagem);

            // Verifica se o usuário foi encontrado
            if (!$user) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => HttpCodesEnum::UserNotFound->description(),
                ]);
            }

            // Verifica se a foto foi encontrada
            if (!$photo) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => 'Foto não encontrada.',
                ]);
            }

            // Busca a pasta à qual a foto pertence
            $pasta = Pastas::find($photo->pasta_id);

            if (!$pasta) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => 'Pasta da foto não encontrada.',
                ]);
            }

            // Verifica se a pasta pertence ao usuário
            if ($pasta->idUsuario !== $user->id) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::Forbidden->value,
                    'message' => 'Você não tem permissão para excluir esta imagem.',
                ]);
            }

            // Extrai o caminho relativo da imagem
            $imagePath = str_replace('/storage/', '', $photo->path);

            // Remove a imagem física do storage
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            // Salva informações antes de deletar
            ComparacaoImagem::where('id_photo', $photo->id)->delete();
            $imageName = basename($photo->path);
            $pastaName = $pasta->nome;

            // Remove o registro da foto do banco de dados
            $photo->delete();

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Imagem excluída com sucesso!',
                'detalhes' => [
                    'imagem_excluida' => $imageName,
                    'pasta' => $pastaName,
                    'total_fotos_restantes' => Photos::where('pasta_id', $pasta->id)->count()
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::InternalServerError->value,
                'message' => 'Erro interno do servidor ao excluir a imagem.',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Recupera informações de uma pasta específica
     * 
     * Busca e retorna os dados completos de uma pasta pelo ID, incluindo suas imagens e subpastas.
     * 
     * @param Request $request - Deve conter: idPasta (ID da pasta)
     * @return JsonResponse - Dados completos da pasta com suas imagens e subpastas ou erro se não encontrada
     */
    public function getFolder(Request $request): JsonResponse
    {
        $request->validate([
            'idPasta' => 'required|integer|exists:pastas,id',
        ]);

        $pasta = Pastas::with(['photos', 'subpastas.photos'])->find($request->idPasta);

        if (!$pasta) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => HttpCodesEnum::NotFound->description()
            ]);
        }

        $response = [
            'codRetorno' => HttpCodesEnum::OK->value,
            'message' => HttpCodesEnum::OK->description(),
            'data' => [
                'id' => $pasta->id,
                'nome' => $pasta->nome,
                'path' => Helper::formatFolderUrl($pasta),
                'subpastas' => $pasta->subpastas->map(function ($subpasta) {
                    return [
                        'id' => $subpasta->id,
                        'nome' => $subpasta->nome,
                        'path' => Helper::formatFolderUrl($subpasta),
                        'imagens' => $subpasta->photos->map(function ($photo) {
                            return [
                                'id' => $photo->id,
                                'path' => Helper::formatImageUrl($photo->path),
                                //'taken_at' => $photo->taken_at ? $photo->taken_at->format('d/m/Y') : null
                            ];
                        })->values()->toArray(),
                        'tags' => $subpasta->tags ? $subpasta->tags->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'nome' => $tag->nomeTag ?? $tag->nomeTag
                            ];
                        })->values()->toArray() : [],
                    ];
                })->values()
            ]
        ];

        return response()->json($response);
    }

    /**
     * Sanitiza o nome da pasta para criação física, removendo acentos e caracteres especiais
     * 
     * @param string $name - Nome original da pasta
     * @return string - Nome sanitizado para uso no sistema de arquivos
     */
    private function sanitizeFolderName(string $name): string
    {
        // Remove acentos e caracteres especiais
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        // Remove caracteres que não são alfanuméricos, hífens, underscores ou espaços
        $name = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $name);

        // Substitui espaços por underscores
        $name = preg_replace('/\s+/', '_', $name);

        // Remove múltiplos underscores consecutivos
        $name = preg_replace('/_+/', '_', $name);

        // Remove underscores no início e fim
        $name = trim($name, '_');

        // Se o nome ficou vazio, usa um nome padrão
        if (empty($name)) {
            $name = 'pasta_' . time();
        }

        return $name;
    }

    /**
     * Lista as pastas principais disponíveis do usuário para ajudar no debug
     * 
     * @param Usuarios $user - Usuário para listar as pastas
     * @return string - String com nomes das pastas separados por vírgula
     */

    /**
     * Lista as pastas principais disponíveis do usuário com detalhes para debug avançado
     * 
     * @param Usuarios $user - Usuário para listar as pastas
     * @return array - Array com detalhes das pastas disponíveis
     */
    private function listarPastasDisponiveisDetalhado(Usuarios $user): array
    {
        $pastas = Pastas::where('idUsuario', $user->id)
            ->whereNull('idPastaPai')
            ->get(['id', 'nome']);

        if ($pastas->isEmpty()) {
            return ['mensagem' => 'Nenhuma pasta principal encontrada'];
        }

        $resultado = [];
        foreach ($pastas as $pasta) {
            $resultado[] = [
                'id' => $pasta->id,
                'nome_original' => $pasta->nome,
                'nome_sanitizado' => $this->sanitizeFolderName($pasta->nome)
            ];
        }

        return $resultado;
    }

    public function retrieveSubFolderImages(Request $request)
    {
        try {
            $request->validate([
                'idPasta' => 'required|exists:pastas,id',
            ]);

            $folder = Pastas::find($request->idPasta);

            if (!$folder) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::NotFound->value,
                    'message' => 'Pasta não encontrada.',
                ], 404);
            }
            // Verifica se é uma subpasta
            if (is_null($folder->idPastaPai)) {
                return response()->json([
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => 'A pasta informada não é uma subpasta.',
                ], 400);
            }

            // Recupera as imagens da subpasta
            $images = $folder->photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'path' => Helper::formatImageUrl($photo->path),
                    'taken_at' => $photo->taken_at ? $photo->taken_at->format('d/m/Y') : null
                ];
            })->values();

            return response()->json([
                'codRetorno' => HttpCodesEnum::OK->value,
                'message' => 'Imagens da subpasta recuperadas com sucesso!',
                'folder_id' => $folder->id,
                'images' => $images,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' => 'Pasta não encontrada.',
            ], 404);
        }
    }
}
