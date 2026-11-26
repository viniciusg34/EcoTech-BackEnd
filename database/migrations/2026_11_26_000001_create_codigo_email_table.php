<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CÓDIGO DE 6 DÍGITOS POR E-MAIL
 * ---------------------------------------------------------------
 * Usado em três situações (coluna "finalidade"):
 *   confirmar_email  -> depois do cadastro, para ativar o e-mail da pessoa
 *   login            -> última etapa do login (pessoa: depois da pergunta; empresa: depois da senha)
 *   recuperar_senha  -> "esqueci a senha"
 *
 * O código NUNCA fica salvo em texto: guardamos só o hash (HMAC com a APP_KEY).
 * uuid = o "desafio_id" que o front recebe.
 * Vale 10 minutos, aceita 5 erros e pode ser reenviado (com intervalo de 60 s).
 * Na recuperação, depois de acertar, o token de redefinição (10 min) fica guardado como hash.
 *
 * Também cria pessoa.email_confirmado. O padrão é TRUE para as contas que já
 * existiam (seed, contas antigas); o cadastro novo grava FALSE até a confirmação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigo_email', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresa')->cascadeOnDelete();
            $table->string('email', 255);
            $table->enum('finalidade', ['confirmar_email', 'login', 'recuperar_senha']);
            $table->char('codigo_hash', 64);
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedTinyInteger('max_tentativas')->default(5);
            $table->unsignedTinyInteger('reenvios')->default(0);
            $table->boolean('lembrar')->default(false);
            $table->dateTime('enviado_em');
            $table->dateTime('expira_em');
            $table->dateTime('resolvido_em')->nullable();
            $table->dateTime('bloqueado_em')->nullable();
            $table->char('token_reset_hash', 64)->nullable()->unique();
            $table->dateTime('token_reset_expira_em')->nullable();
            $table->dateTime('token_reset_usado_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['email', 'finalidade']);
        });

        Schema::table('pessoa', function (Blueprint $table) {
            $table->boolean('email_confirmado')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('pessoa', function (Blueprint $table) {
            $table->dropColumn('email_confirmado');
        });
        Schema::dropIfExists('codigo_email');
    }
};
