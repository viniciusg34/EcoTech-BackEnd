<?php

namespace Database\Seeders;

use App\Models\CategoriaMaterial;
use App\Models\Ecoponto;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
 * 3 empresas FICTÍCIAS e os 22 Ecopontos do mapa do front (js/data-map-points.js).
 * Senha das empresas de exemplo: SEED_EMPRESA_PASSWORD no .env (padrão Ecotech2026).
 */
class EmpresaEcopontoSeeder extends Seeder
{
    public function run(): void
    {
        $senha = env('SEED_EMPRESA_PASSWORD', 'Ecotech2026');

        $empresas = [
            'costa'  => ['11222333000181', 'Recicla Costa Verde', 'Recicla Costa Verde Ltda (fictícia)', 'contato@costaverde.ecotech.example', 'Mangaratiba - RJ'],
            'rio'    => ['44555666000181', 'Rio Recicla Soluções Ambientais', 'Rio Recicla Soluções Ambientais Ltda (fictícia)', 'contato@riorecicla.ecotech.example', 'Rio de Janeiro - RJ'],
            'metro'  => ['77888999000181', 'Metropolitana Verde Reciclagem', 'Metropolitana Verde Reciclagem Ltda (fictícia)', 'contato@metroverde.ecotech.example', 'Niterói - RJ'],
        ];

        $ids = [];
        foreach ($empresas as $apelido => [$cnpj, $nome, $razao, $email, $endereco]) {
            $empresa = Empresa::query()->where('cnpj', $cnpj)->first() ?? new Empresa();
            $empresa->forceFill([
                'cnpj' => $cnpj, 'nome_empresa' => $nome, 'razao_social' => $razao, 'email' => $email,
                'senha_hash' => $empresa->senha_hash ?: Hash::make($senha), 'endereco' => $endereco, 'status' => 'ativa',
            ])->save();
            $ids[$apelido] = $empresa->id;
        }

        $categorias = CategoriaMaterial::query()->pluck('id', 'chave');

        // [código, nome, materiais, descrição, lat, lng, bairro/região, cidade, empresa]
        $pontos = [
            ['ECO-01', 'Centro Recicla Mangaratiba', ['papel', 'madeira'], 'Papelão, caixotes e restos de madeira', -22.9597, -44.0406, 'Centro', 'Mangaratiba', 'costa'],
            ['ECO-02', 'EcoPonto Vila Muriqui', ['metal', 'plastico'], 'Latinhas, ferragens e embalagens plásticas', -22.9245, -43.9538, 'Vila Muriqui', 'Mangaratiba', 'costa'],
            ['ECO-03', 'Itacuruçá Plásticos', ['plastico', 'vidro'], 'Garrafas PET, tampinhas e potes de vidro', -22.9291, -43.8860, 'Itacuruçá', 'Mangaratiba', 'costa'],
            ['ECO-04', 'Jacareí Vidros', ['vidro', 'metal'], 'Potes de vidro e sucata metálica', -23.0188, -44.2081, 'Conceição de Jacareí', 'Mangaratiba', 'costa'],
            ['ECO-05', 'EcoCentro Metal', ['metal', 'madeira'], 'Sucata de metal e paletes de madeira', -22.9068, -43.1729, 'Centro', 'Rio de Janeiro', 'rio'],
            ['ECO-06', 'Vidro Lapa', ['vidro', 'papel'], 'Garrafas de vidro e papelão', -22.9122, -43.1769, 'Lapa', 'Rio de Janeiro', 'rio'],
            ['ECO-07', 'EcoPapel Botafogo', ['papel', 'madeira', 'plastico'], 'Papel, móveis quebrados e embalagens plásticas', -22.9519, -43.2105, 'Botafogo', 'Rio de Janeiro', 'rio'],
            ['ECO-08', 'TechRecicla Copacabana', ['eletronicos', 'metal'], 'Eletrônicos, pilhas e sucata metálica', -22.9711, -43.1823, 'Copacabana', 'Rio de Janeiro', 'rio'],
            ['ECO-09', 'Ipanema Plásticos', ['plastico', 'vidro'], 'PET, sacolas e frascos de vidro', -22.9836, -43.2044, 'Ipanema', 'Rio de Janeiro', 'rio'],
            ['ECO-10', 'Tijuca Sustentável', ['papel', 'madeira'], 'Jornais, caixas e restos de obra em madeira', -22.9329, -43.2372, 'Tijuca', 'Rio de Janeiro', 'rio'],
            ['ECO-11', 'Méier Metais', ['metal', 'eletronicos'], 'Sucata metálica e eletrônicos', -22.9015, -43.2801, 'Méier', 'Rio de Janeiro', 'rio'],
            ['ECO-12', 'Madureira Vidros', ['vidro', 'plastico'], 'Vidros e embalagens plásticas', -22.8760, -43.3364, 'Madureira', 'Rio de Janeiro', 'rio'],
            ['ECO-13', 'Ilha Eco-Tech', ['eletronicos', 'metal', 'plastico'], 'Eletrônicos, metais e plásticos', -22.8122, -43.2144, 'Ilha do Governador', 'Rio de Janeiro', 'rio'],
            ['ECO-14', 'Barra PET Coleta', ['plastico', 'papel'], 'Garrafas PET e papel', -23.0003, -43.3658, 'Barra da Tijuca', 'Rio de Janeiro', 'metro'],
            ['ECO-15', 'Recreio Vidros', ['vidro', 'madeira'], 'Vidros e madeira', -23.0181, -43.4682, 'Recreio dos Bandeirantes', 'Rio de Janeiro', 'metro'],
            ['ECO-16', 'Bangu Eletrônicos', ['eletronicos', 'metal'], 'Eletrônicos e metais', -22.8752, -43.4650, 'Bangu', 'Rio de Janeiro', 'metro'],
            ['ECO-17', 'Campo Grande Plast', ['plastico', 'vidro'], 'Plásticos e vidros', -22.8986, -43.5598, 'Campo Grande', 'Rio de Janeiro', 'metro'],
            ['ECO-18', 'Jacarepaguá Papéis', ['papel', 'madeira', 'metal'], 'Papel, madeira e metal', -22.9678, -43.3888, 'Jacarepaguá', 'Rio de Janeiro', 'metro'],
            ['ECO-19', 'Icaraí Metais (Niterói)', ['metal', 'vidro'], 'Metais e vidros', -22.9056, -43.1065, 'Icaraí', 'Niterói', 'metro'],
            ['ECO-20', 'São Gonçalo Plásticos', ['plastico', 'papel'], 'Plásticos e papel', -22.8269, -43.0538, 'Centro', 'São Gonçalo', 'metro'],
            ['ECO-21', 'Nova Iguaçu Vidros', ['vidro', 'metal', 'madeira'], 'Vidros, metais e madeira', -22.7562, -43.4607, 'Centro', 'Nova Iguaçu', 'metro'],
            ['ECO-22', 'Duque de Caxias Papel', ['papel', 'plastico'], 'Papel e plásticos', -22.7856, -43.3117, 'Centro', 'Duque de Caxias', 'metro'],
        ];

        foreach ($pontos as [$codigo, $nome, $cats, $descricao, $lat, $lng, $bairro, $cidade, $empresa]) {
            $ecoponto = Ecoponto::query()->where('codigo', $codigo)->first() ?? new Ecoponto();
            $ecoponto->fill([
                'empresa_id' => $ids[$empresa], 'codigo' => $codigo, 'nome' => $nome, 'descricao' => $descricao,
                'endereco' => $bairro, 'cidade' => $cidade, 'estado' => 'RJ', 'lat' => $lat, 'lng' => $lng,
                'status' => 'ativo',
            ]);
            $ecoponto->segredo_qr = $ecoponto->segredo_qr ?: bin2hex(random_bytes(32));
            $ecoponto->save();
            $ecoponto->categorias()->sync(array_map(fn ($c) => $categorias[$c], $cats));
        }
    }
}
