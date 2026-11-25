<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        // [chave, rótulo, cor, moedas por unidade, unidade, ícone] — mesma ordem e cores do mapa do front
        $categorias = [
            ['metal', 'Metal', '#f9a825', 20, 'kg', '🥫'],
            ['papel', 'Papel', '#66bb6a', 8, 'kg', '📦'],
            ['vidro', 'Vidro', '#42a5f5', 6, 'kg', '🫙'],
            ['plastico', 'Plástico', '#ef5350', 12, 'kg', '🧴'],
            ['eletronicos', 'Eletrônicos', '#ab47bc', 25, 'un', '🔋'],
            ['madeira', 'Madeira', '#8d6e63', 5, 'kg', '🪵'],
        ];

        foreach ($categorias as [$chave, $rotulo, $cor, $coins, $unidade, $icone]) {
            CategoriaMaterial::query()->updateOrCreate(['chave' => $chave], [
                'rotulo' => $rotulo, 'cor_hex' => $cor, 'coins_por_unidade' => $coins, 'unidade' => $unidade, 'icone' => $icone,
            ]);
        }
    }
}
