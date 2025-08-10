<?php

namespace App\Jobs;

use App\Http\Util\Helper;
use App\Models\Usuarios;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para bloqueio automático de usuários inativos
 * 
 * Verifica todos os usuários e bloqueia aqueles que não acessaram
 * o sistema dentro do período de renovação semestral definido.
 * Executado automaticamente pela fila de jobs.
 */
class BlockUser implements ShouldQueue
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
     * Executa o job de bloqueio de usuários inativos
     * 
     * Percorre todos os usuários e verifica se o último acesso
     * excedeu o período limite. Se sim, bloqueia o usuário
     * alterando seu status para 0 (inativo).
     * 
     * @return void
     */
    public function handle()
    {
        Log::info('Processo iniciado.');

        // Resetando o contador de pastas criadas para todos os usuários no mês
        try {
            $usuarios = Usuarios::all();

            foreach ($usuarios as $usuario) {
                $dataLimiteAcesso = Carbon::parse($usuario->ultimoAcesso)->addDays(Helper::TEMPO_RENOVACAO_SEMESTRAL);
                if (Helper::checkDateIsPassed($dataLimiteAcesso)) {
                    $usuario->status = 0;
                    $usuario->save();
                }
            }

            Log::info('Processo finalizado.');
        } catch (\Exception $e) {
            Log::error('Erro ao resetar contador de pastas: ' . $e->getMessage());
        }
    }
}
