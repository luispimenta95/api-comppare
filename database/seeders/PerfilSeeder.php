<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfilSeeder extends Seeder
{
    public function run()
    {
        DB::table('perfil')->insert([
            ['id' => 1, 'nome_perfil' => 'Administrativo', 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'nome_perfil' => 'Usuário', 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'nome_perfil' => 'Convidados', 'created_at' => null, 'updated_at' => null],
        ]);
    }
}
