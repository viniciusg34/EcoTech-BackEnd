<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CONFIGURAÇÃO: valores que mudam sem mexer no código
 * (moedas do check-in, minutos da sessão do Ecoponto etc.).
 * "publico" = aparece no GET /config para o front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracao', function (Blueprint $table) {
            $table->string('chave', 50)->primary();
            $table->string('valor', 255);
            $table->string('descricao', 255)->nullable();
            $table->boolean('publico')->default(false);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracao');
    }
};
