<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PagamentoPix;
use App\Enums\MeioPagamentoEnum;

class UpdateUsuariosMeioPagamentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Atualizando meio de pagamento dos usuários...');
        
        // Opção 1: Atualizar baseado no histórico de pagamentos PIX
        $usuariosComPix = PagamentoPix::whereIn('status', ['APROVADA', 'PENDENTE'])
            ->distinct()
            ->pluck('idUsuario');
            
        User::whereIn('id', $usuariosComPix)
            ->update(['meioPagamento' => MeioPagamentoEnum::PIX]);
            
        $this->command->info('Usuários com histórico PIX: ' . $usuariosComPix->count());
        
        // Opção 2: Usuários sem histórico PIX ficam com cartão
        $usuariosSemPix = User::whereNotIn('id', $usuariosComPix)->get();
        
        foreach ($usuariosSemPix as $usuario) {
            $usuario->meioPagamento = MeioPagamentoEnum::CARTAO;
            $usuario->save();
        }
        
        $this->command->info('Usuários configurados para cartão: ' . $usuariosSemPix->count());
        
        // Opção 3: Lógica personalizada (exemplo)
        // User::where('created_at', '>', '2024-01-01')
        //     ->update(['meioPagamento' => MeioPagamentoEnum::PIX]);
        
        $this->command->info('Atualização concluída!');
    }
}
