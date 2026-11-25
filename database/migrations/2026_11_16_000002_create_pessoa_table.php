<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PESSOA: quem recicla (perfil "comum") e o administrador (perfil "master").
 * CPF, CEP e telefones ficam só com números.
 * saldo_coins é um "cache" do livro-razão (movimento_coins): quem muda é o CoinService.
 * Excluir pessoa = anonimizar (LGPD): os dados pessoais somem, o histórico fica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->string('email', 255)->unique();
            $table->char('cpf', 11)->nullable()->unique();     // NULL só depois de anonimizada
            $table->char('login', 6)->nullable()->unique();    // exatamente 6 letras
            $table->string('senha_hash', 255);
            $table->string('nome_mae', 150)->nullable();
            $table->date('nascimento')->nullable();
            $table->enum('sexo', ['feminino', 'masculino', 'outro', 'nao_informar'])->nullable();
            $table->string('telefone_celular', 11)->nullable(); // DDD + 9 dígitos
            $table->string('telefone_fixo', 10)->nullable();    // DDD + 8 dígitos
            $table->char('cep', 8)->nullable();
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->enum('perfil', ['comum', 'master'])->default('comum');
            $table->string('avatar_url', 400)->nullable();
            $table->unsignedBigInteger('saldo_coins')->default(0);
            $table->string('nivel_visto', 30)->nullable();      // último nível já mostrado (confete)
            $table->json('preferencias')->nullable();            // ex.: notificações do perfil
            $table->dateTime('termos_aceitos_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->dateTime('anonimizado_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });

        Banco::check('pessoa', 'chk_pessoa_saldo', 'saldo_coins >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa');
    }
};
