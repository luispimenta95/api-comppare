<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanosSeeder extends Seeder
{
    public function run()
    {
        DB::table('planos')->insert([
            ['id' => 1, 'nome' => 'Plano Gratuito', 'descricao' => 'Plano Gratuito', 'valor' => 0, 'tempoGratuidade' => 360, 'quantidadeTags' => 3, 'quantidadeFotos' => 5, 'quantidadePastas' => 4, 'frequenciaCobranca' => 0, 'status' => 1, 'idHost' => null, 'created_at' => null, 'updated_at' => null, 'quantidadeConvites' => 0],
            ['id' => 2, 'nome' => 'Plano de Filiados', 'descricao' => 'Plano de Filiados', 'valor' => 0, 'tempoGratuidade' => 180, 'quantidadeTags' => 10, 'quantidadeFotos' => 15, 'quantidadePastas' => 40, 'frequenciaCobranca' => 0, 'status' => 1, 'idHost' => null, 'created_at' => null, 'updated_at' => null, 'quantidadeConvites' => 0],
            ['id' => 3, 'nome' => 'Plano Básico', 'descricao' => 'Plano Básico', 'valor' => 24.9, 'tempoGratuidade' => 15, 'quantidadeTags' => 6, 'quantidadeFotos' => 2, 'quantidadePastas' => 20, 'frequenciaCobranca' => 1, 'status' => 1, 'idHost' => 122812, 'created_at' => '2025-03-18 14:45:59', 'updated_at' => '2025-03-18 14:45:59', 'quantidadeConvites' => 0],
            ['id' => 4, 'nome' => 'Plano Básico Anual', 'descricao' => 'Plano Básico Anual', 'valor' => 239, 'tempoGratuidade' => 15, 'quantidadeTags' => 6, 'quantidadeFotos' => 2, 'quantidadePastas' => 20, 'frequenciaCobranca' => 12, 'status' => 1, 'idHost' => 122813, 'created_at' => '2025-03-18 14:48:42', 'updated_at' => '2025-03-18 14:48:42', 'quantidadeConvites' => 0],
            ['id' => 5, 'nome' => 'Plano Avançado', 'descricao' => 'Plano Avançado', 'valor' => 39.9, 'tempoGratuidade' => 15, 'quantidadeTags' => 10, 'quantidadeFotos' => 2, 'quantidadePastas' => 40, 'frequenciaCobranca' => 1, 'status' => 1, 'idHost' => 122814, 'created_at' => '2025-03-18 14:51:14', 'updated_at' => '2025-03-18 14:51:14', 'quantidadeConvites' => 0],
            ['id' => 6, 'nome' => 'Plano Avançado Anual', 'descricao' => 'Plano Avançado Anual', 'valor' => 383, 'tempoGratuidade' => 15, 'quantidadeTags' => 10, 'quantidadeFotos' => 2, 'quantidadePastas' => 40, 'frequenciaCobranca' => 12, 'status' => 1, 'idHost' => 122815, 'created_at' => '2025-03-18 14:53:11', 'updated_at' => '2025-03-18 14:53:11', 'quantidadeConvites' => 0],
            ['id' => 7, 'nome' => 'Plano de Convidados', 'descricao' => 'Plano de Convidados', 'valor' => 0, 'tempoGratuidade' => 360, 'quantidadeTags' => 0, 'quantidadeFotos' => 0, 'quantidadePastas' => 0, 'frequenciaCobranca' => 0, 'status' => 1, 'idHost' => null, 'created_at' => '2025-03-25 19:32:44', 'updated_at' => '2025-03-25 19:33:00', 'quantidadeConvites' => 0],
        ]);

        DB::table('planos')->whereIn('id', [2, 7])->update(['exibicao' => 0]);
    }
}
