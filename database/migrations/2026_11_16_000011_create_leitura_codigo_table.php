<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * LEITURA DE CÓDIGO DE BARRAS: toda leitura fica registrada, até as recusadas.
 * idempotency_key = a mesma leitura reenviada (internet ruim) não credita duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leitura_codigo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->restrictOnDelete();
            $table->foreignId('ecoponto_id')->nullable()->constrained('ecoponto')->nullOnDelete();
            $table->foreignId('sessao_id')->nullable()->constrained('sessao_ecoponto')->nullOnDelete();
            $table->string('codigo_barras', 14);
            $table->string('produto_codigo', 14)->nullable();
            $table->enum('resultado', ['creditado', 'duplicado', 'nao_participa', 'invalido']);
            $table->unsignedInteger('coins_creditados')->default(0);
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->dateTime('lido_em');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('produto_codigo')->references('codigo_barras')->on('produto_catalogo')->nullOnDelete();
            $table->index(['codigo_barras', 'pessoa_id', 'lido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leitura_codigo');
    }
};
