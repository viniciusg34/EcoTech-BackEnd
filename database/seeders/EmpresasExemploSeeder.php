<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use App\Models\Ecoponto;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
 * MAIS 6 EMPRESAS FICTÍCIAS, cada uma com 2 Ecopontos (ECO-23 a ECO-34).
 * Complementa o EmpresaEcopontoSeeder (as 3 primeiras empresas e os 22 pontos do mapa).
 *
 * php artisan db:seed --class=EmpresasExemploSeeder --force
 *
 * Pode rodar de novo: atualiza quem já existe (pelo CNPJ / código), não duplica.
 * E-mails terminam em ".ecotech.example" (domínio reservado, nunca recebe e-mail):
 * para entrar com elas use o CODIGO_DEMO do .env no lugar do código por e-mail.
 * Senha: SEED_EMPRESA_PASSWORD do .env (padrão Ecotech2026), igual às outras empresas de exemplo.
 */
class EmpresasExemploSeeder extends Seeder
{
    public function run(): void
    {
        $senha = env('SEED_EMPRESA_PASSWORD', 'Ecotech2026');

        // apelido => [base do CNPJ (8 dígitos), nome, razão social, e-mail, endereço, status]
        $empresas = [
            'angra'     => ['31415926', 'Angra Limpa Reciclagem', 'Angra Limpa Reciclagem Ltda (fictícia)', 'contato@angralimpa.ecotech.example', 'Angra dos Reis - RJ', 'ativa'],
            'itaguai'   => ['27182818', 'Itaguaí Recicla', 'Itaguaí Recicla Comércio de Sucatas Ltda (fictícia)', 'contato@itaguairecicla.ecotech.example', 'Itaguaí - RJ', 'ativa'],
            'serra'     => ['16180339', 'Serra Verde Ambiental', 'Serra Verde Ambiental Ltda (fictícia)', 'contato@serraverde.ecotech.example', 'Petrópolis - RJ', 'ativa'],
            'baixada'   => ['14142135', 'Baixada Sustentável', 'Baixada Sustentável Cooperativa (fictícia)', 'contato@baixadasustentavel.ecotech.example', 'Belford Roxo - RJ', 'ativa'],
            'zonanorte' => ['17320508', 'Zona Norte E-Lixo', 'Zona Norte Eletrônicos Recicláveis Ltda (fictícia)', 'contato@zonanorteelixo.ecotech.example', 'Rio de Janeiro - RJ', 'ativa'],
            'paraty'    => ['22360679', 'Paraty Mar Limpo', 'Paraty Mar Limpo Associação (fictícia)', 'contato@paratymarlimpo.ecotech.example', 'Paraty - RJ', 'suspensa'],
        ];

        $ids = [];
        foreach ($empresas as $apelido => [$base, $nome, $razao, $email, $endereco, $status]) {
            $cnpj = self::cnpjValido($base);
            $empresa = Empresa::query()->where('cnpj', $cnpj)->first() ?? new Empresa();
            $empresa->forceFill([
                'cnpj' => $cnpj, 'nome_empresa' => $nome, 'razao_social' => $razao, 'email' => $email,
                'senha_hash' => $empresa->senha_hash ?: Hash::make($senha), 'endereco' => $endereco, 'status' => $status,
            ])->save();
            $ids[$apelido] = $empresa->id;
            $this->command?->line("  empresa: {$nome} ({$email})" . ($status !== 'ativa' ? " [{$status}]" : ''));
        }

        $categorias = CategoriaMaterial::query()->pluck('id', 'chave');

        // [código, nome, materiais, descrição, lat, lng, bairro, cidade, empresa, status]
        $pontos = [
            ['ECO-23', 'Angra Centro Recicla', ['plastico', 'metal'], 'Garrafas PET, latinhas e tampinhas', -23.0067, -44.3181, 'Centro', 'Angra dos Reis', 'angra', 'ativo'],
            ['ECO-24', 'Frade Vidros e Papel', ['vidro', 'papel'], 'Vidros e papelão', -22.9706, -44.4413, 'Frade', 'Angra dos Reis', 'angra', 'ativo'],
            ['ECO-25', 'Itaguaí Metais', ['metal', 'eletronicos'], 'Sucata metálica, pilhas e eletrônicos', -22.8521, -43.7753, 'Centro', 'Itaguaí', 'itaguai', 'ativo'],
            ['ECO-26', 'Coroa Grande PET', ['plastico', 'vidro'], 'PET e potes de vidro', -22.9097, -43.8636, 'Coroa Grande', 'Itaguaí', 'itaguai', 'ativo'],
            ['ECO-27', 'Petrópolis Centro Histórico', ['papel', 'vidro'], 'Papel, jornais e garrafas de vidro', -22.5097, -43.1779, 'Centro', 'Petrópolis', 'serra', 'ativo'],
            ['ECO-28', 'Itaipava Madeira e Metal', ['madeira', 'metal'], 'Paletes, móveis velhos e sucata', -22.3866, -43.1328, 'Itaipava', 'Petrópolis', 'serra', 'manutencao'],
            ['ECO-29', 'Belford Roxo Coleta', ['plastico', 'papel', 'metal'], 'Plásticos, papel e metais', -22.7641, -43.3995, 'Centro', 'Belford Roxo', 'baixada', 'ativo'],
            ['ECO-30', 'Mesquita Recicla', ['vidro', 'plastico'], 'Vidros e embalagens plásticas', -22.7828, -43.4316, 'Centro', 'Mesquita', 'baixada', 'ativo'],
            ['ECO-31', 'Penha E-Lixo', ['eletronicos', 'metal'], 'Celulares, cabos, pilhas e baterias', -22.8418, -43.2779, 'Penha', 'Rio de Janeiro', 'zonanorte', 'ativo'],
            ['ECO-32', 'Vila Isabel Eletrônicos', ['eletronicos', 'plastico'], 'Eletrônicos pequenos e plásticos', -22.9155, -43.2441, 'Vila Isabel', 'Rio de Janeiro', 'zonanorte', 'ativo'],
            ['ECO-33', 'Paraty Cais Limpo', ['plastico', 'vidro'], 'Plásticos e vidros da orla', -23.2178, -44.7131, 'Centro Histórico', 'Paraty', 'paraty', 'inativo'],
            ['ECO-34', 'Trindade Praia Limpa', ['plastico', 'metal'], 'Plásticos e latinhas das praias', -23.3483, -44.7258, 'Trindade', 'Paraty', 'paraty', 'inativo'],
        ];

        foreach ($pontos as [$codigo, $nome, $cats, $descricao, $lat, $lng, $bairro, $cidade, $empresa, $status]) {
            $ecoponto = Ecoponto::query()->where('codigo', $codigo)->first() ?? new Ecoponto();
            $ecoponto->fill([
                'empresa_id' => $ids[$empresa], 'codigo' => $codigo, 'nome' => $nome, 'descricao' => $descricao,
                'endereco' => $bairro, 'cidade' => $cidade, 'estado' => 'RJ', 'lat' => $lat, 'lng' => $lng,
                'status' => $status,
            ]);
            $ecoponto->segredo_qr = $ecoponto->segredo_qr ?: bin2hex(random_bytes(32));
            $ecoponto->save();
            $ecoponto->categorias()->sync(array_map(fn ($c) => $categorias[$c], $cats));
        }

        $this->command?->info('  ' . count($empresas) . ' empresas e ' . count($pontos) . ' Ecopontos de exemplo prontos.');
    }

    // CNPJ fictício com dígitos verificadores certos: 8 dígitos + "0001" + 2 dígitos
    public static function cnpjValido(string $base8): string
    {
        $numeros = str_pad($base8, 8, '0', STR_PAD_LEFT) . '0001';
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $numeros[$i] * $peso;
            }
            $resto = $soma % 11;
            $numeros .= $resto < 2 ? '0' : (string) (11 - $resto);
        }

        return $numeros;
    }
}
