<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\PagamentoPix;
use App\Enums\MeioPagamentoEnum;
use Illuminate\Support\Facades\DB;

class UpdateUsuariosMeioPagamento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usuarios:update-meio-pagamento 
                            {--strategy=auto : Estratégia de atualização (auto, pix, cartao, historico)}
                            {--dry-run : Simular sem fazer alterações}
                            {--force : Forçar atualização sem confirmação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atualiza o meio de pagamento dos usuários existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $strategy = $this->option('strategy');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info("Iniciando atualização com estratégia: {$strategy}");
        
        if ($dryRun) {
            $this->warn('MODO SIMULAÇÃO - Nenhuma alteração será feita');
        }
        
        $totalUsuarios = User::count();
        $this->info("Total de usuários: {$totalUsuarios}");
        
        switch ($strategy) {
            case 'pix':
                $this->updateAllToPix($dryRun);
                break;
                
            case 'cartao':
                $this->updateAllToCartao($dryRun);
                break;
                
            case 'historico':
                $this->updateBasedOnHistory($dryRun);
                break;
                
            case 'auto':
            default:
                $this->updateAutomatic($dryRun);
                break;
        }
        
        if (!$dryRun) {
            $this->info('✅ Atualização concluída!');
        } else {
            $this->info('📋 Simulação concluída. Use --dry-run=false para executar');
        }
    }
    
    private function updateAllToPix($dryRun = false)
    {
        $this->info('Atualizando todos os usuários para PIX...');
        
        if (!$dryRun) {
            $updated = User::query()->update(['meioPagamento' => MeioPagamentoEnum::PIX]);
            $this->info("✅ {$updated} usuários atualizados para PIX");
        } else {
            $count = User::count();
            $this->info("📋 {$count} usuários seriam atualizados para PIX");
        }
    }
    
    private function updateAllToCartao($dryRun = false)
    {
        $this->info('Atualizando todos os usuários para CARTÃO...');
        
        if (!$dryRun) {
            $updated = User::query()->update(['meioPagamento' => MeioPagamentoEnum::CARTAO]);
            $this->info("✅ {$updated} usuários atualizados para CARTÃO");
        } else {
            $count = User::count();
            $this->info("📋 {$count} usuários seriam atualizados para CARTÃO");
        }
    }
    
    private function updateBasedOnHistory($dryRun = false)
    {
        $this->info('Atualizando baseado no histórico de pagamentos...');
        
        // Usuários com histórico PIX
        $usuariosComPix = PagamentoPix::whereIn('status', ['APROVADA', 'PENDENTE'])
            ->distinct()
            ->pluck('idUsuario');
            
        $this->info("Usuários com histórico PIX: {$usuariosComPix->count()}");
        
        if (!$dryRun) {
            User::whereIn('id', $usuariosComPix)
                ->update(['meioPagamento' => MeioPagamentoEnum::PIX]);
                
            $usuariosSemPix = User::whereNotIn('id', $usuariosComPix)
                ->update(['meioPagamento' => MeioPagamentoEnum::CARTAO]);
                
            $this->info("✅ Usuários configurados para PIX: {$usuariosComPix->count()}");
            $this->info("✅ Usuários configurados para CARTÃO: {$usuariosSemPix}");
        } else {
            $semPix = User::whereNotIn('id', $usuariosComPix)->count();
            $this->info("📋 {$usuariosComPix->count()} usuários seriam configurados para PIX");
            $this->info("📋 {$semPix} usuários seriam configurados para CARTÃO");
        }
    }
    
    private function updateAutomatic($dryRun = false)
    {
        $this->info('Executando atualização automática inteligente...');
        
        // Lógica automática: PIX para quem tem histórico, senão mantém padrão
        $usuariosComPix = PagamentoPix::whereIn('status', ['APROVADA', 'PENDENTE'])
            ->distinct()
            ->pluck('idUsuario');
            
        if (!$dryRun) {
            if ($usuariosComPix->count() > 0) {
                User::whereIn('id', $usuariosComPix)
                    ->update(['meioPagamento' => MeioPagamentoEnum::PIX]);
                    
                $this->info("✅ {$usuariosComPix->count()} usuários com histórico PIX mantidos");
            }
            
            // Novos usuários e sem histórico ficam com PIX (padrão do sistema)
            $this->info("✅ Outros usuários mantêm PIX como padrão");
        } else {
            $this->info("📋 {$usuariosComPix->count()} usuários com histórico PIX seriam mantidos");
            $this->info("📋 Outros usuários manteriam PIX como padrão");
        }
    }
}
