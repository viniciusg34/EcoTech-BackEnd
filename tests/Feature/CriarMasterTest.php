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

    public function test_email_do_master_ja_usado_por_conta_comum_nao_altera_nada_e_o_seed_continua(): void
    {
        $this->seed(DatabaseSeeder::class);                       // master com master@ecotech.com
        $antes = $this->master();
        $comum = $this->criarPessoa(['email' => 'souvinicius2004@gmail.com', 'cpf' => $this->cpfValido(123456789)]);

        // Igual ao servidor: MASTER_EMAIL passou a ser o Gmail que já tem conta comum
        config(['ecotech.master.email' => 'souvinicius2004@gmail.com']);

        $this->artisan('ecotech:criar-master')
            ->expectsOutputToContain('MASTER_EMAIL: o e-mail souvinicius2004@gmail.com já é da conta id ' . $comum->id)
            ->assertFailed();

        // A conta comum continua comum, com a mesma senha e o mesmo CPF
        $comum->refresh();
        $this->assertSame('comum', $comum->perfil);
        $this->assertTrue(Hash::check('abcdefgh', $comum->senha_hash));
        $this->assertNotSame($antes->cpf, $comum->cpf);
        // O master continua o mesmo
        $this->assertSame($antes->id, $this->master()->id);
        $this->assertSame('master@ecotech.com', $this->master()->email);

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
            ->expectsOutputToContain('MASTER_CPF: o CPF 52998224725 já é da conta id')
            ->assertFailed();
        $this->assertSame(0, Pessoa::query()->where('perfil', 'master')->count());
    }
}
