<?php

namespace App\Jobs;

use App\Models\Usuarios;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para resetar contador de pastas criadas pelos usuários
 * 
 * Executa mensalmente para zerar o contador de pastas criadas
 * de todos os usuários, permitindo que criem novas pastas
 * conforme o limite do seu plano no novo período.
 */
class ResetPastasCounter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nome e assinatura do comando de console
     *
     * @var string
     */
    protected $signature = 'resetPastasCounter';

    /**
     * Descrição do comando de console
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Executa o reset do contador de pastas para todos os usuários
     * 
     * Percorre todos os usuários do sistema e zera o campo
     * 'pastasCriadas', permitindo que criem novas pastas
     * no novo período mensal conforme limite do plano.
     * 
     * @return void
     */
    public function handle()
    {

        // Resetando o contador de pastas criadas para todos os usuários no mês
        try {
            $usuarios = Usuarios::all();

            foreach ($usuarios as $usuario) {
                // Resetar contadores de pastas principais e subpastas
                $usuario->pastasCriadas = 0;
                $usuario->subpastasCriadas = 0;
                $usuario->save();
            }

            Log::info('Contadores de pastas e subpastas resetados com sucesso para todos os usuários.');

            } catch (\Exception $e) {
            Log::error('Erro ao resetar contadores de pastas e subpastas: ' . $e->getMessage());
        }
    }
}
