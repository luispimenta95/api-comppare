<?php

namespace Database\Seeders;

use App\Models\Perfil;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Planos;
use App\Models\Usuarios;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Perfil::create([
            'nome_perfil' => 'Administrador', // Primeiro tipo de perfil

        ]);

        Perfil::create([
            'nome_perfil' => 'Usuário', // Segundo tipo de perfil

        ]);

        Planos::create([
            'nome' => 'Plano Premium',
            'descricao' => 'Plano Premium',
            'valor' => 100,
            'quantidadeTags' => 10,
        ]);

        Usuarios::create([
            'nome' => 'Administrador',
            'email' => 'teste@gmail.com',
            'senha' => bcrypt('13151319'),
            'idPerfil' => 1,
            'cpf' => '12345678909',
            'telefone' => '11999999999',
            'idPlano' => 1,
        ]);

    }
}
