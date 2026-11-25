<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MOVIMENTO DE COINS (livro-razão / ledger): SÓ INSERT, nunca UPDATE nem DELETE.
 * Toda moeda que entra (+) ou sai (-) vira uma linha aqui, com o saldo depois dela.
 * Assim dá para provar de onde veio cada moeda do saldo da pessoa.
 * descricao/subtitulo/icone já guardam o texto que aparece no extrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimento_coins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->restrictOnDelete();
            $table->enum('tipo', ['checkin', 'entrega', 'leitura', 'resgate', 'estorno', 'ajuste']);
            $table->integer('valor');                            // negativo no débito
            $table->unsignedBigInteger('saldo_apos');
            $table->string('origem_tipo', 30)->nullable();       // ex.: leitura_codigo, coleta, resgate
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('descricao', 255);
            $table->string('subtitulo', 150)->nullable();
            $table->string('icone', 10)->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['pessoa_id', 'criado_em']);
        });

        Banco::check('movimento_coins', 'chk_movimento_valor', 'valor <> 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('movimento_coins');
    }
};
