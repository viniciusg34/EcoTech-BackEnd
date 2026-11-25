<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * EMPRESA: parceira dona dos Ecopontos. Entra com e-mail e senha (sem 2FA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->char('cnpj', 14)->unique();                 // só números
            $table->string('nome_empresa', 150);
            $table->string('razao_social', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('senha_hash', 255);
            $table->string('endereco', 255)->nullable();
            $table->enum('status', ['ativa', 'suspensa'])->default('ativa');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa');
    }
};
