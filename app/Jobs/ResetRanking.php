<?php

namespace App\Jobs;

use App\Models\Usuarios;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetRanking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resetRanking';

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

        // Resetando o contador de pastas criadas para todos os usuários no mês
        try {
            $usuarios = Usuarios::all();

            foreach ($usuarios as $usuario) {
                $usuario->pontos = 0;
                $usuario->save();
            }

            Log::info('Ranking atualizado com pontuação zerada.');

    
        } catch (\Exception $e) {
            Log::error('Erro ao resetar ranking: ' . $e->getMessage());
        }
    }
}
