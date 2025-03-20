<?php

namespace App\Jobs;

use App\Http\Util\Helper;
use App\Models\Cupom;
use App\Models\Usuarios;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
class ResetTickets implements ShouldQueue
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
            $cupons = Cupom::all();

            foreach ($cupons as $cupom) {
                if (Helper::checkDateIsPassed(Carbon::parse($cupom->dataExpiracao))) {
                    $cupom->status = 0;
                    $cupom->save();
                }
            }
            Log::info('Processo finalizado.');

        } catch (\Exception $e) {
            Log::error('Erro ao resetar contador de pastas: ' . $e->getMessage());
        }
    }
}
