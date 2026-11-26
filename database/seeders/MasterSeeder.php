<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

// O master usa os dados MASTER_* do .env (a senha nunca fica no código)
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('ecotech.master.senha')) {
            $this->command?->warn('MASTER_PASSWORD não está no .env: o master não foi criado. Depois rode: php artisan ecotech:criar-master');
            return;
        }
        // Se o master não puder ser criado/atualizado, só avisa: o resto do seed (e o deploy) continua
        $codigo = Artisan::call('ecotech:criar-master');
        $saida = trim(Artisan::output());
        $codigo === 0 ? $this->command?->info($saida) : $this->command?->warn($saida);
    }
}
