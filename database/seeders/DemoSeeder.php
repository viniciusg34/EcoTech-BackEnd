<?php

namespace Database\Seeders;

use App\Exceptions\ApiException;
use App\Models\Ecoponto;
use App\Models\Pessoa;
use App\Models\Recompensa;
use App\Services\CheckinService;
use App\Services\ColetaService;
use App\Services\ResgateService;
use App\Services\SessaoEcopontoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/*
 * DADOS DE DEMONSTRAÇÃO (para a apresentação e para testar o front)
 * ---------------------------------------------------------------
 * php artisan db:seed --class=DemoSeeder
 *
 * Cria 3 pessoas fictícias para entrar no site (anasil, brunof, carlam) e mais
 * 12 moradores, todos com 6 meses de histórico (check-ins, entregas e um
 * resgate), usando os próprios serviços da API: o saldo bate com o livro-razão
 * e os painéis ganham gráficos de verdade. No total dá cerca de 3 toneladas
 * recicladas, para o site mostrar números reais. Não roda em produção.
 * Senha das pessoas: SEED_DEMO_PASSWORD no .env (padrão "Reciclar1", que segue a regra forte).
 * Pergunta de segurança: nome da mãe "Maria José da Silva", nascimento
 * 12/05/1990 e CEP 20040-020 (as três pessoas).
 * Pode rodar de novo: quem já existe é pulado.
 */
class DemoSeeder extends Seeder
{
    public function run(SessaoEcopontoService $sessoes, CheckinService $checkins, ColetaService $coletas, ResgateService $resgates): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder não roda em produção.');
            return;
        }
        if (Ecoponto::query()->count() === 0) {
            $this->call(DatabaseSeeder::class);
        }

        mt_srand(2026);
        Carbon::setTestNow();
        $agora = Carbon::now();   // hora real (o relógio "volta no tempo" durante o seeder)
        $senha = Hash::make((string) env('SEED_DEMO_PASSWORD', 'Reciclar1'));
        $pessoas = [
            ['Ana Maria da Silva', 'ana@demo.ecotech.example', 'anasil', '39053344705'],
            ['Bruno Costa Ferreira', 'bruno@demo.ecotech.example', 'brunof', '71428793860'],
            ['Carla Mendes Rocha', 'carla@demo.ecotech.example', 'carlam', '82178537464'],
        ];
        // Moradores que só aparecem nas estatísticas, nos painéis e nas listas do master
        $moradores = [
            ['Diego Alves Pereira', 'diegoa'], ['Elisa Ramos Duarte', 'elisar'], ['Fabio Nunes Teixeira', 'fabion'],
            ['Gabriela Lima Souza', 'gabils'], ['Heitor Barros Campos', 'heitob'], ['Isabela Moura Pinto', 'isabem'],
            ['Joao Pedro Cardoso', 'joaope'], ['Larissa Faria Gomes', 'larisf'], ['Marcos Vieira Lopes', 'marcov'],
            ['Natalia Rezende Cruz', 'natalr'], ['Otavio Freitas Melo', 'otavif'], ['Paula Andrade Reis', 'paulaa'],
        ];
        foreach ($moradores as $i => [$nomeMorador, $loginMorador]) {
            $pessoas[] = [$nomeMorador, "{$loginMorador}@demo.ecotech.example", $loginMorador, self::cpfValido(900000 + $i)];
        }
        $pontos = Ecoponto::query()->where('status', 'ativo')->with('categorias')->orderBy('id')->get();

        foreach ($pessoas as [$nome, $email, $login, $cpf]) {
            if (Pessoa::query()->where('email', $email)->orWhere('login', $login)->orWhere('cpf', $cpf)->exists()) {
                $this->command?->line("  já existe: {$email}");
                continue;
            }

            $inicio = $agora->copy()->subMonths(5)->startOfMonth()->setTime(9, 0);
            $pessoa = new Pessoa();
            $pessoa->forceFill([
                'nome' => $nome, 'email' => $email, 'login' => $login, 'cpf' => $cpf, 'senha_hash' => $senha,
                'nome_mae' => 'Maria José da Silva', 'nascimento' => '1990-05-12', 'sexo' => 'nao_informar',
                'telefone_celular' => '21999990000', 'cep' => '20040020', 'logradouro' => 'Rua da Assembleia', 'numero' => '10',
                'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ', 'perfil' => 'comum', 'saldo_coins' => 0,
                'termos_aceitos_em' => $inicio, 'ativo' => true,
            ]);
            $pessoa->criado_em = $inicio;
            $pessoa->save();

            // Visitas: de 1 a 3 por mês, nos últimos 6 meses (o relógio "volta no tempo")
            for ($mes = 5; $mes >= 0; $mes--) {
                $base = $agora->copy()->subMonths($mes)->startOfMonth();
                $ultimoDia = $mes === 0 ? max(1, $agora->day - 1) : $base->daysInMonth;
                $dias = collect(range(1, $ultimoDia))->shuffle()->take(mt_rand(2, 5))->sort();
                foreach ($dias as $dia) {
                    $quando = $base->copy()->day($dia)->setTime(mt_rand(8, 18), mt_rand(0, 59));
                    if ($quando->greaterThanOrEqualTo($agora)) {
                        continue;
                    }
                    Carbon::setTestNow($quando);
                    $ponto = $pontos[mt_rand(0, $pontos->count() - 1)];
                    $sessao = $sessoes->conectar($pessoa, $ponto);
                    try {
                        $checkins->fazer($pessoa, $sessao);
                        $itens = $ponto->categorias->shuffle()->take(mt_rand(1, 2))->map(fn ($c) => [
                            'categoria'  => $c->chave,
                            // por entrega: de 2 a 15 kg (sacolas de uma casa) ou de 1 a 3 unidades
                            'quantidade' => $c->unidade === 'un' ? mt_rand(1, 3) : mt_rand(20, 150) / 10,
                        ])->values()->all();
                        Carbon::setTestNow($quando->copy()->addMinutes(3));
                        $coletas->registrarEntrega($ponto, $sessao, $itens);
                    } catch (ApiException) {
                        // cooldown ou material não aceito: só pula esta visita
                    } finally {
                        $sessoes->encerrar($sessao);
                    }
                }
            }

            // Um resgate no mês passado, se der
            Carbon::setTestNow($agora->copy()->subMonth()->endOfMonth()->setTime(12, 0));
            $cafe = Recompensa::query()->where('nome', 'Café Grátis')->first();
            if ($cafe && $pessoa->fresh()->saldo_coins >= $cafe->custo_coins) {
                $resgates->resgatar($pessoa, $cafe->id);
            }
            Carbon::setTestNow();

            $this->command?->info("  {$nome} ({$login}): " . $pessoa->fresh()->saldo_coins . ' coins');
        }

        Carbon::setTestNow();
    }

    // CPF fictício com dígitos verificadores certos (a partir de um número base)
    private static function cpfValido(int $base): string
    {
        $numeros = str_pad((string) $base, 9, '0', STR_PAD_LEFT);
        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $numeros[$i] * (($posicao + 1) - $i);
            }
            $numeros .= ((10 * $soma) % 11) % 10;
        }
        return $numeros;
    }
}
