<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// Dados que não dependem de senha (usado nos testes automáticos)
class BaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConfiguracaoSeeder::class,
            CategoriaSeeder::class,
            RecompensaSeeder::class,
            ProdutoSeeder::class,
        ]);
    }
}
