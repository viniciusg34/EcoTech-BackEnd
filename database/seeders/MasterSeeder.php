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
        Artisan::call('ecotech:criar-master');
        $this->command?->info(trim(Artisan::output()));
    }
}
