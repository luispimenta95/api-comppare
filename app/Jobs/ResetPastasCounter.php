<?php

namespace App\Jobs;

use App\Models\Usuarios;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetPastasCounter implements ShouldQueue
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
        // Resetando o contador de pastas criadas para todos os usuários no mês
        try {
            $usuarios = Usuarios::all();

            foreach ($usuarios as $usuario) {
                // Se você tiver um atributo que conta o número de pastas criadas, resetar ele aqui
                // Exemplo: $usuario->pastasCriadas = 0;
                // O código abaixo é só um exemplo, modifique conforme sua lógica de contagem

                $usuario->pastasCriadas = 0;
                $usuario->save();
            }

            Log::info('Contador de pastas resetado com sucesso para todos os usuários.');
        } catch (\Exception $e) {
            Log::error('Erro ao resetar contador de pastas: ' . $e->getMessage());
        }
    }
}
