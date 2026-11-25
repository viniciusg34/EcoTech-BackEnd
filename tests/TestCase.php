<?php

namespace Tests;

use App\Models\CategoriaMaterial;
use App\Models\Configuracao;
use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Services\AuthService;
use App\Services\EcopontoQrService;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/*
 * Base dos testes: banco SQLite em memória, refeito a cada teste,
 * com categorias, produtos, recompensas e configurações já cadastrados.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = BaseSeeder::class;

    /*
     * Proteção: os testes APAGAM e recriam as tabelas. Só deixa rodar num banco
     * de teste (SQLite em memória ou um banco com "test" no nome), nunca no
     * banco de verdade do .env (por exemplo, o do Aiven).
     */
    protected function beforeRefreshingDatabase(): void
    {
        $conexao = config('database.connections.' . config('database.default'), []);
        $banco = strtolower((string) ($conexao['database'] ?? ''));

        if (! empty($conexao['url']) || ($banco !== ':memory:' && ! str_contains($banco, 'test'))) {
            throw new \RuntimeException(
                "Testes cancelados: o banco \"{$banco}\" não parece ser de teste e os testes apagam as tabelas. "
                . 'Rode com o phpunit.xml do projeto (SQLite em memória).'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Configuracao::limparMemoria();
        // Nenhum teste chama APIs de verdade (Open Food Facts, ViaCEP, Google)
        Http::preventStrayRequests();
    }

    protected function criarPessoa(array $dados = []): Pessoa
    {
        static $n = 0;
        $n++;
        $pessoa = new Pessoa();
        $pessoa->forceFill(array_merge([
            'nome'             => 'Pessoa de Teste Número ' . $n,
            'email'            => "pessoa{$n}@teste.com",
            'cpf'              => $this->cpfValido(100000000 + $n),
            'login'            => substr('abcdefghijklmnopqrstuvwxyz', $n % 20, 5) . chr(97 + ($n % 26)),
            'senha_hash'       => Hash::make('abcdefgh'),
            'nome_mae'         => 'Maria José da Silva',
            'nascimento'       => '1990-05-12',
            'sexo'             => 'feminino',
            'telefone_celular' => '21999991234',
            'cep'              => '20040020',
            'logradouro'       => 'Rua da Assembleia',
            'numero'           => '10',
            'bairro'           => 'Centro',
            'cidade'           => 'Rio de Janeiro',
            'uf'               => 'RJ',
            'perfil'           => 'comum',
            'saldo_coins'      => 0,
            'ativo'            => true,
            'termos_aceitos_em' => now(),
        ], $dados))->save();

        return $pessoa;
    }

    protected function criarMaster(): Pessoa
    {
        return $this->criarPessoa(['perfil' => 'master', 'nome' => 'Administrador Master EcoTech']);
    }

    // Token direto (sem passar pelo 2FA), para os testes que não são de login
    protected function token(Pessoa|Empresa $conta, bool $lembrar = false): string
    {
        return app(AuthService::class)->emitirToken($conta, $lembrar)['token'];
    }

    // Próximas chamadas vão com o token desta conta (troca de conta limpa o usuário anterior)
    protected function comToken(Pessoa|Empresa $conta): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($conta));
    }

    protected function criarEmpresa(array $dados = []): Empresa
    {
        static $n = 0;
        $n++;
        $empresa = new Empresa();
        $empresa->forceFill(array_merge([
            'cnpj'         => $this->cnpjValido(10000000 + $n),
            'nome_empresa' => "Empresa Teste {$n}",
            'email'        => "empresa{$n}@teste.com",
            'senha_hash'   => Hash::make('Empresa123'),
            'status'       => 'ativa',
        ], $dados))->save();

        return $empresa;
    }

    protected function criarEcoponto(Empresa $empresa, array $dados = [], array $categorias = ['metal', 'plastico', 'eletronicos']): Ecoponto
    {
        static $n = 0;
        $n++;
        $ecoponto = new Ecoponto();
        $ecoponto->forceFill(array_merge([
            'empresa_id' => $empresa->id,
            'codigo'     => 'TST-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'nome'       => "Ecoponto Teste {$n}",
            'cidade'     => 'Rio de Janeiro',
            'estado'     => 'RJ',
            'lat'        => -22.9,
            'lng'        => -43.2,
            'status'     => 'ativo',
            'segredo_qr' => bin2hex(random_bytes(32)),
        ], $dados))->save();
        $ecoponto->categorias()->sync(CategoriaMaterial::query()->whereIn('chave', $categorias)->pluck('id'));

        return $ecoponto;
    }

    protected function qr(Ecoponto $ecoponto, bool $dinamico = false): string
    {
        return app(EcopontoQrService::class)->payload($ecoponto, $dinamico)['payload'];
    }

    // Pessoa conectada a um Ecoponto (sessão ativa). Devolve [pessoa, empresa, ecoponto, sessao_id]
    protected function sessaoAtiva(?Pessoa $pessoa = null): array
    {
        $pessoa ??= $this->criarPessoa();
        $empresa = $this->criarEmpresa();
        $ecoponto = $this->criarEcoponto($empresa);
        $sessaoId = $this->comToken($pessoa)->postJson('/api/v1/ecopontos/conectar', ['qr' => $this->qr($ecoponto)])
            ->assertOk()->json('sessao_id');
        $this->flushHeaders();

        return [$pessoa, $empresa, $ecoponto, $sessaoId];
    }

    protected function cpfValido(int $base9): string
    {
        $d = str_split(str_pad((string) $base9, 9, '0', STR_PAD_LEFT));
        foreach ([9, 10] as $n) {
            $s = 0;
            for ($i = 0; $i < $n; $i++) {
                $s += (int) $d[$i] * ($n + 1 - $i);
            }
            $d[] = (string) ((10 * $s % 11) % 10);
        }
        return implode('', $d);
    }

    protected function cnpjValido(int $base8): string
    {
        $b = str_pad((string) $base8, 8, '0', STR_PAD_LEFT) . '0001';
        $p1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $s = 0;
        foreach (str_split($b) as $i => $c) {
            $s += (int) $c * $p1[$i];
        }
        $b .= $s % 11 < 2 ? 0 : 11 - $s % 11;
        $p2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $s = 0;
        foreach (str_split($b) as $i => $c) {
            $s += (int) $c * $p2[$i];
        }
        return $b . ($s % 11 < 2 ? 0 : 11 - $s % 11);
    }
}
