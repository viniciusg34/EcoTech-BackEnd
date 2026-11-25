<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RESGATE: a troca de moedas por uma recompensa. Gera um código de cupom único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resgate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->restrictOnDelete();
            $table->foreignId('recompensa_id')->constrained('recompensa')->restrictOnDelete();
            $table->unsignedInteger('custo_coins');
            $table->string('codigo_cupom', 20)->unique();
            $table->enum('status', ['emitido', 'utilizado', 'cancelado'])->default('emitido');
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['pessoa_id', 'criado_em']);
        });

        Banco::check('resgate', 'chk_resgate_custo', 'custo_coins > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('resgate');
    }
};
