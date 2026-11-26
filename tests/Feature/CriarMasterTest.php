<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
 * ecotech:criar-master nunca pode transformar uma conta comum em master,
 * e um conflito (e-mail/CPF/login de outra conta) não pode derrubar o seed nem o deploy.
 */
class CriarMasterTest extends TestCase
{
    private function master(): Pessoa
    {
        return Pessoa::query()->where('perfil', 'master')->sole();
    }

    public function test_email_do_master_ja_usado_por_conta_comum_mantem_o_email_mas_troca_a_senha(): void
    {
        $this->seed(DatabaseSeeder::class);                       // master com master@ecotech.com
        $antes = $this->master();
        $comum = $this->criarPessoa(['email' => 'souvinicius2004@gmail.com', 'cpf' => $this->cpfValido(123456789)]);

        // Igual ao servidor: MASTER_EMAIL passou a ser o Gmail que já tem conta comum, e a senha mudou
        config(['ecotech.master.email' => 'souvinicius2004@gmail.com', 'ecotech.master.senha' => 'NovaSenha9']);

        $this->artisan('ecotech:criar-master')
            ->expectsOutputToContain('MASTER_EMAIL: souvinicius2004@gmail.com já é da conta id ' . $comum->id)
            ->assertSuccessful();

        // A conta comum continua comum, com a mesma senha
        $comum->refresh();
        $this->assertSame('comum', $comum->perfil);
        $this->assertTrue(Hash::check('abcdefgh', $comum->senha_hash));
        // O master é o mesmo, com o e-mail antigo e a senha NOVA
        $master = $this->master();
        $this->assertSame($antes->id, $master->id);
        $this->assertSame('master@ecotech.com', $master->email);
        $this->assertSame($antes->cpf, $master->cpf);
        $this->assertTrue(Hash::check('NovaSenha9', $master->senha_hash));

        // E o seed inteiro não quebra (o deploy segue)
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, Pessoa::query()->where('perfil', 'master')->count());
    }

    public function test_trocar_master_email_para_um_livre_atualiza_o_master_que_ja_existe(): void
    {
        $this->seed(DatabaseSeeder::class);
        $id = $this->master()->id;

        config(['ecotech.master.email' => 'admin.ecotech@gmail.com']);
        $this->artisan('ecotech:criar-master')->assertSuccessful();

        $this->assertSame($id, $this->master()->id);
        $this->assertSame('admin.ecotech@gmail.com', $this->master()->email);
        $this->assertSame(1, Pessoa::query()->where('perfil', 'master')->count());
    }

    public function test_cpf_do_master_usado_por_outra_conta_avisa_em_vez_de_quebrar(): void
    {
        $this->criarPessoa(['cpf' => '52998224725']);   // alguém se cadastrou com o CPF padrão do master

        $this->artisan('ecotech:criar-master')
            ->expectsOutputToContain('MASTER_CPF: 52998224725 já é da conta id')
            ->assertFailed();
        $this->assertSame(0, Pessoa::query()->where('perfil', 'master')->count());
    }
}
