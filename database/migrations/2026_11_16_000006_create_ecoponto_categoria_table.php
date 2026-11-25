<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ECOPONTO x CATEGORIA (N:N): quais materiais cada Ecoponto aceita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecoponto_categoria', function (Blueprint $table) {
            $table->foreignId('ecoponto_id')->constrained('ecoponto')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categoria_material')->restrictOnDelete();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['ecoponto_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecoponto_categoria');
    }
};
