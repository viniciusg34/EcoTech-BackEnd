<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TOKEN DE ACESSO (Laravel Sanctum). É a "sessão" da pessoa ou da empresa:
 * o front manda "Authorization: Bearer <token>" e a API sabe quem é.
 *   tokenable_type = 'pessoa' ou 'empresa' (tipo da conta)
 *   tokenable_id   = id da conta
 *   token          = hash SHA-256 do token (o token em si nunca é guardado)
 *   abilities      = papéis do token: pessoa, master ou empresa
 *   expires_at     = 8 horas, ou 30 dias com "lembrar de mim"
 * No logout a linha é apagada (token revogado).
 * Os nomes das colunas são os que o Sanctum exige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_acesso', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_acesso');
    }
};
