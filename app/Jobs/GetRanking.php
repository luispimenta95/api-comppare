<?php

namespace App\Jobs;

use App\Models\Ponto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GetRanking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getRanking';

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

        // Obter o ranking
        $ranking = Ponto::selectRaw('idUsuario, SUM(pontos) as total')
            ->groupBy('idUsuario')
            ->orderByDesc('total')
            ->with('usuario')
            ->get();

        // Criar o nome do arquivo com a data atual
        $fileName = 'log_' . now()->format('Y-m-d') . '.txt';

        // Criar o conteúdo do arquivo
        $content = "Ranking de pontos - " . now()->format('Y-m-d') . "\n\n";

        foreach ($ranking as $rank) {
            // Adicionar cada usuário e sua pontuação no arquivo
            $content .= "Usuário ID: {$rank->idUsuario}, Nome: {$rank->usuario->nome}, Pontos: {$rank->pontos}\n";
        }

        // Salvar o conteúdo no arquivo dentro da pasta storage/logs
        Storage::disk('local')->put('logs/' . $fileName, $content);

        // Caso você queira verificar no storage público, você pode usar 'public' no lugar de 'local'
        // Storage::disk('public')->put('logs/' . $fileName, $content);
        Log::info('Ranking atualizado com pontuação zerada.');

        Log::info('Processo finalizado.');
    }

}
