<?php

namespace Tests\Feature;

use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\MovimentoCoins;
use App\Models\Pessoa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * Importação do banco antigo: monta um "banco antigo" de mentira (SQLite em memória)
 * com as mesmas colunas das tabelas do Aiven e confere o resultado.
 */
class ImportarLegadoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legado' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legado');

        $s = Schema::connection('legado');
        $s->create('empresa', function (Blueprint $t) {
            foreach (['empre_id', 'name', 'email', 'password', 'cnpj', 'adress'] as $c) {
                $t->string($c)->nullable();
            }
        });
        $s->create('ecopontos', function (Blueprint $t) {
            foreach (['eco_id', 'name', 'code', 'adress', 'city', 'state', 'cats', 'empre_id'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('status')->default(1);
            $t->decimal('lat', 9, 6)->nullable();
            $t->decimal('lng', 9, 6)->nullable();
        });
        $s->create('recompensas', function (Blueprint $t) {
            foreach (['reward_id', 'cat', 'icon', 'name', 'desc_text'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('cost');
        });
        $s->create('usuario', function (Blueprint $t) {
            foreach (['user_id', 'first_name', 'last_name', 'birth_day', 'sex', 'mothers_fullname', 'cpf', 'email', 'phone_number', 'adress', 'password', 'avatar', 'reg_date', 'cep', 'tipo_usuario'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('coins')->default(0);
        });

        $l = DB::connection('legado');
        $l->table('empresa')->insert(['empre_id' => 'uuid-emp-1', 'name' => 'Recicla Antiga', 'email' => 'Antiga@Empresa.com', 'password' => 'senha123', 'cnpj' => '11.222.333/0001-81']);
        $l->table('ecopontos')->insert([
            ['eco_id' => 'e1', 'name' => 'Ponto Antigo', 'code' => 'ant-01', 'state' => 'Rio de Janeiro', 'cats' => 'Plástico, metal', 'empre_id' => 'uuid-emp-1', 'status' => 1, 'lat' => -22.9, 'lng' => -43.2],
            ['eco_id' => 'e2', 'name' => 'Sem empresa', 'code' => 'ant-02', 'state' => 'RJ', 'cats' => 'papel', 'empre_id' => 'nao-existe', 'status' => 1, 'lat' => -22.9, 'lng' => -43.2],
        ]);
        $l->table('recompensas')->insert(['reward_id' => 'r1', 'cat' => 'food', 'icon' => '🍕', 'name' => 'Pizza Grátis', 'desc_text' => 'Uma fatia', 'cost' => 300]);
        $l->table('usuario')->insert([
            ['user_id' => 'u1', 'first_name' => 'Carla', 'last_name' => 'Mendes Rocha', 'birth_day' => '1995-03-02', 'sex' => 'Feminino',
             'mothers_fullname' => 'Rita Mendes', 'cpf' => '529.982.247-25', 'email' => 'Carla@Teste.com', 'phone_number' => '(21) 98888-7777',
             'adress' => 'Rua A, 1', 'password' => 'textopuro', 'avatar' => '', 'reg_date' => '2026-08-10', 'cep' => '20040-020', 'tipo_usuario' => 'comum', 'coins' => 340.7],
            ['user_id' => 'u2', 'first_name' => 'Admin', 'last_name' => 'Antigo', 'birth_day' => null, 'sex' => null, 'mothers_fullname' => null,
             'cpf' => '000', 'email' => 'admin@antigo.com', 'phone_number' => null, 'adress' => null, 'password' => Hash::make('jahashed'),
             'avatar' => '', 'reg_date' => null, 'cep' => '20040020', 'tipo_usuario' => 'admin', 'coins' => 0],
        ]);
    }

    public function test_importa_e_converte_os_dados_antigos(): void
    {
        $this->artisan('ecotech:importar-legado')->assertSuccessful();

        $empresa = Empresa::query()->where('email', 'antiga@empresa.com')->first();
        $this->assertSame('11222333000181', $empresa->cnpj);
        $this->assertTrue(Hash::check('senha123', $empresa->senha_hash));

        $ponto = Ecoponto::query()->where('codigo', 'ANT-01')->with('categorias')->first();
        $this->assertSame($empresa->id, $ponto->empresa_id);
        $this->assertSame('RJ', $ponto->estado);
        $this->assertEqualsCanonicalizing(['plastico', 'metal'], $ponto->categorias->pluck('chave')->all());
        $this->assertNull(Ecoponto::query()->where('codigo', 'ANT-02')->first());

        $carla = Pessoa::query()->where('email', 'carla@teste.com')->first();
        $this->assertSame('Carla Mendes Rocha', $carla->nome);
        $this->assertSame('52998224725', $carla->cpf);
        $this->assertSame('21988887777', $carla->telefone_celular);
        $this->assertSame('feminino', $carla->sexo);
        $this->assertTrue(Hash::check('textopuro', $carla->senha_hash));
        $this->assertSame(340, $carla->saldo_coins);
        $this->assertSame(1, MovimentoCoins::query()->where('pessoa_id', $carla->id)->where('tipo', 'ajuste')->count());

        $admin = Pessoa::query()->where('email', 'admin@antigo.com')->first();
        $this->assertSame('master', $admin->perfil);
        $this->assertNull($admin->cpf);
        $this->assertTrue(Hash::check('jahashed', $admin->senha_hash));

        $this->assertDatabaseHas('recompensa', ['nome' => 'Pizza Grátis', 'custo_coins' => 300]);

        // Rodar de novo não duplica
        $this->artisan('ecotech:importar-legado')->assertSuccessful();
        $this->assertSame(2, Pessoa::query()->count());
        $this->assertSame(1, Empresa::query()->count());
    }

    public function test_simular_nao_grava_nada(): void
    {
        $this->artisan('ecotech:importar-legado --simular')->assertSuccessful();
        $this->assertSame(0, Pessoa::query()->count());
        $this->assertSame(0, Empresa::query()->count());
    }

    public function test_importa_os_json_da_mockapi_com_reciclado(): void
    {
        $pasta = sys_get_temp_dir() . '/mockapi-' . uniqid();
        mkdir($pasta);
        file_put_contents("{$pasta}/empresa.json", json_encode([
            ['id' => '1', 'nome' => 'Recicla Mock', 'cnpj' => '44.555.666/0001-81', 'email' => 'mock@empresa.com', 'senha' => 'Mock1234', 'endereco' => 'Rua B, 2', 'kgColetado' => '10.00'],
        ]));
        file_put_contents("{$pasta}/ecopontos.json", json_encode([
            ['id' => '1', 'empresaId' => '1', 'code' => 'ECO-01', 'name' => 'Ponto Mock', 'lat' => -22.9, 'lng' => -43.2, 'cats' => ['papel', 'madeira']],
        ]));
        file_put_contents("{$pasta}/pessoa.json", json_encode([
            ['id' => '7', 'nome' => 'Diego Alves Pereira', 'email' => 'diego@teste.com', 'cpf' => '529.982.247-25', 'login' => 'diegoa',
             'senha' => 'abcdefgh', 'nomeMae' => 'Rosa Alves', 'nascimento' => '12/05/1990', 'sexo' => 'Masculino',
             'telefoneCelular' => '(21) 97777-6666', 'cep' => '20040-020', 'perfil' => 'comum', 'coins' => '80.00', 'createdAt' => '2026-07-01T10:00:00.000Z',
             'reciclado' => [
                 ['name' => 'Desconhecido', 'pts' => 50, 'codigo' => 'ECO-99', 'time' => '11:00'],
                 ['name' => 'Ponto Mock', 'pts' => 50, 'codigo' => 'ECO-01', 'time' => '10:00'],
             ]],
            ['id' => '8', 'nome' => 'Elisa Gomes Ribeiro', 'email' => 'elisa@teste.com', 'senha' => 'abcdefgh', 'perfil' => 'master', 'coins' => 20,
             'reciclado' => [['pts' => 50, 'codigo' => 'ECO-01'], ['pts' => 50, 'codigo' => 'eco-01']]],
        ]));

        $this->artisan('ecotech:importar-legado', ['--mockapi' => $pasta])->assertSuccessful();
        array_map('unlink', glob("{$pasta}/*.json"));
        rmdir($pasta);

        $diego = Pessoa::query()->where('email', 'diego@teste.com')->first();
        $this->assertSame('1990-05-12', $diego->nascimento->format('Y-m-d'));
        $this->assertSame('masculino', $diego->sexo);
        $this->assertSame('diegoa', $diego->login);
        $this->assertSame(80, $diego->saldo_coins);   // 50 do reciclado + 30 de ajuste
        $this->assertSame(['checkin', 'ajuste'], MovimentoCoins::query()->where('pessoa_id', $diego->id)->orderBy('id')->pluck('tipo')->all());
        $this->assertSame(1, \App\Models\Coleta::query()->where('pessoa_id', $diego->id)->count());

        $elisa = Pessoa::query()->where('email', 'elisa@teste.com')->first();
        $this->assertSame('master', $elisa->perfil);
        $this->assertSame(20, $elisa->saldo_coins);   // 100 do reciclado - 80 já gastos
        $this->assertSame(-80, MovimentoCoins::query()->where('pessoa_id', $elisa->id)->where('tipo', 'ajuste')->value('valor'));

        $ponto = Ecoponto::query()->where('codigo', 'ECO-01')->with('categorias')->first();
        $this->assertSame('Recicla Mock', $ponto->empresa->nome_empresa);
        $this->assertEqualsCanonicalizing(['papel', 'madeira'], $ponto->categorias->pluck('chave')->all());
    }

    public function test_pasta_inexistente_falha(): void
    {
        $this->artisan('ecotech:importar-legado', ['--mockapi' => '/nao/existe'])->assertFailed();
    }
}
