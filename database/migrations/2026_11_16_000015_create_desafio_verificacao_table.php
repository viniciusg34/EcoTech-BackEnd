<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DESAFIO DE VERIFICAÇÃO: a pergunta do 2FA (login) e da recuperação de senha.
 * A resposta é conferida no servidor; o front nunca recebe nome da mãe, nascimento ou CEP.
 * uuid = o "desafio_id" que o front recebe (não dá para adivinhar o de outra pessoa).
 * Máximo de 3 tentativas e validade de 5 minutos; depois o desafio fica bloqueado.
 * Na recuperação, depois de acertar, o token de redefinição (10 min) fica guardado como hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desafio_verificacao', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('conta_tipo', ['pessoa', 'empresa'])->default('pessoa');
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->nullOnDelete();
            $table->enum('finalidade', ['login', 'recuperar_senha']);
            $table->enum('pergunta', ['nome_mae', 'nascimento', 'cep']);
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedTinyInteger('max_tentativas')->default(3);
            $table->boolean('lembrar')->default(false);
            $table->dateTime('expira_em');
            $table->dateTime('resolvido_em')->nullable();
            $table->dateTime('bloqueado_em')->nullable();
            $table->char('token_reset_hash', 64)->nullable()->unique();
            $table->dateTime('token_reset_expira_em')->nullable();
            $table->dateTime('token_reset_usado_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desafio_verificacao');
    }
};
