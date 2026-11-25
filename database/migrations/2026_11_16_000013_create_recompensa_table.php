<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RECOMPENSA: o que dá para trocar pelas moedas.
 * estoque NULL = ilimitado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recompensa', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['food', 'transport', 'health', 'shop', 'leisure']);
            $table->string('icone', 10)->nullable();
            $table->string('nome', 150);
            $table->string('descricao', 255)->nullable();
            $table->unsignedInteger('custo_coins');
            $table->unsignedInteger('estoque')->nullable();
            $table->boolean('ativa')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });

        Banco::check('recompensa', 'chk_recompensa_custo', 'custo_coins > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('recompensa');
    }
};
