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

class BlockUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetPastasCounter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
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
