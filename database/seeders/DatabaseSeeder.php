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
    public function run()
    {
        $this->call([
            PlanosSeeder::class,
            PerfilSeeder::class,
            QuestionsSeeder::class,
        ]);
    }
}
