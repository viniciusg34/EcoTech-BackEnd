<?php

namespace Database\Seeders;

use App\Models\Configuracao;
use Illuminate\Database\Seeder;

// Valores padrão (dá para mudar depois direto na tabela, sem mexer no código)
class ConfiguracaoSeeder extends Seeder
{
    public function run(): void
    {
        $padroes = [
            ['checkin_coins', '50', 'Moedas ganhas em cada check-in', true],
            ['checkin_kg', '0.5', 'Kg contados em cada check-in', true],
            ['checkin_cooldown_horas', '6', 'Horas de espera entre check-ins no mesmo Ecoponto', true],
            ['duplicidade_horas', '24', 'Horas em que o mesmo produto não pode ser lido de novo pela mesma pessoa', true],
            ['sessao_minutos', '10', 'Minutos que a sessão no Ecoponto fica aberta', true],
        ];

        foreach ($padroes as [$chave, $valor, $descricao, $publico]) {
            Configuracao::query()->firstOrCreate(['chave' => $chave], [
                'valor' => $valor, 'descricao' => $descricao, 'publico' => $publico,
            ]);
        }
    }
}
