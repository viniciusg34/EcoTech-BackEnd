<?php

namespace Database\Seeders;

use App\Models\Recompensa;
use Illuminate\Database\Seeder;

class RecompensaSeeder extends Seeder
{
    public function run(): void
    {
        // [categoria, ícone, nome, descrição, custo] — catálogo do front (js/data-rewards.js)
        $recompensas = [
            ['food', '🍔', "Cupom McDonald's", '20% OFF em combos', 200],
            ['food', '☕', 'Café Grátis', 'Starbucks ou cafeteria parceira', 80],
            ['food', '🥤', 'Eco Garrafa', 'Garrafa reutilizável em cafeteria parceira', 120],
            ['transport', '🎫', 'Passe Metrô Rio', '1 viagem gratuita', 150],
            ['transport', '🚲', 'Bike Itaú 1h', '1 hora de uso grátis', 90],
            ['health', '🌱', 'Kit Sementes', 'Sementes de árvores nativas', 100],
            ['health', '💊', 'Desconto Farmácia', '15% OFF em medicamentos', 120],
            ['shop', '🛍️', 'Bolsa Ecotech', 'Sacola sustentável da Ecotech', 180],
            ['shop', '🛍️', 'Vale Compras R$30', 'Lojas parceiras EcoTech', 300],
            ['shop', '👕', 'Camiseta Sustentável', 'Algodão orgânico reciclado', 220],
            ['leisure', '🎭', 'Ingresso de Cinema', 'Meia-entrada em qualquer sessão', 250],
        ];

        foreach ($recompensas as [$categoria, $icone, $nome, $descricao, $custo]) {
            Recompensa::query()->updateOrCreate(['nome' => $nome], [
                'categoria' => $categoria, 'icone' => $icone, 'descricao' => $descricao,
                'custo_coins' => $custo, 'estoque' => null, 'ativa' => true,
            ]);
        }
    }
}
