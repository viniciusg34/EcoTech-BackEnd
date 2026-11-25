<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * SESSÃO NO ECOPONTO: criada quando a pessoa lê o QR da placa.
 * Enquanto está "ativa", o Ecoponto pode ler produtos e as moedas vão para essa pessoa.
 * Expira sozinha depois de N minutos (configuracao.sessao_minutos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessao_ecoponto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecoponto_id')->constrained('ecoponto')->restrictOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoa')->restrictOnDelete();
            $table->char('token', 64)->unique();
            $table->enum('status', ['ativa', 'encerrada', 'expirada'])->default('ativa');
            $table->dateTime('iniciada_em');
            $table->dateTime('expira_em');
            $table->dateTime('encerrada_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['ecoponto_id', 'status']);
            $table->index(['pessoa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessao_ecoponto');
    }
};
