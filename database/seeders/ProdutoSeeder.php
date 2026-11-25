<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use App\Models\ProdutoCatalogo;
use Illuminate\Database\Seeder;

/*
 * Produtos da campanha. Os códigos do PDF tinham o último dígito (verificador) errado
 * em 5 dos 6 produtos; aqui já estão corrigidos (só a Pilha AA estava certa).
 */
class ProdutoSeeder extends Seeder
{
    public function run(): void
    {
        $categoria = CategoriaMaterial::query()->pluck('id', 'chave');

        // [código, nome, moedas, ícone, categoria, peso estimado em kg]
        $produtos = [
            ['7896004004778', 'Garrafa PET 500ml', 30, '🧴', 'plastico', 0.02],
            ['7891234567895', 'Lata de Alumínio 350ml', 40, '🥫', 'metal', 0.02],
            ['7896666000019', 'Caixa de Papelão', 20, '📦', 'papel', 0.20],
            ['7899000001239', 'Vidro de Conserva', 35, '🫙', 'vidro', 0.30],
            ['7891910000197', 'Pilha Alcalina AA', 80, '🔋', 'eletronicos', 0.02],
            ['7896000000002', 'Embalagem Tetra Pak', 25, '🧃', 'papel', 0.03],
        ];

        foreach ($produtos as [$codigo, $nome, $coins, $icone, $chave, $peso]) {
            ProdutoCatalogo::query()->updateOrCreate(['codigo_barras' => $codigo], [
                'nome' => $nome, 'coins' => $coins, 'icone' => $icone,
                'categoria_id' => $categoria[$chave], 'peso_kg' => $peso, 'ativo' => true,
            ]);
        }
    }
}
