<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/*
 * php artisan migrate --seed   (ou  php artisan db:seed)
 * Pode rodar mais de uma vez: não duplica nada.
 * Dados de demonstração (pessoas com histórico): php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConfiguracaoSeeder::class,
            CategoriaSeeder::class,
            RecompensaSeeder::class,
            ProdutoSeeder::class,
            EmpresaEcopontoSeeder::class,
            MasterSeeder::class,
        ]);
    }
}
