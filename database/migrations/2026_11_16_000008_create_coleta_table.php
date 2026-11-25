<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * COLETA: um check-in ou uma entrega de materiais num Ecoponto.
 * total_kg alimenta as estatísticas (toneladas recicladas, painel da empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coleta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoa')->restrictOnDelete();
            $table->foreignId('ecoponto_id')->constrained('ecoponto')->restrictOnDelete();
            $table->foreignId('sessao_id')->nullable()->constrained('sessao_ecoponto')->nullOnDelete();
            $table->enum('tipo', ['checkin', 'entrega']);
            $table->unsignedInteger('total_coins')->default(0);
            $table->decimal('total_kg', 10, 2)->default(0);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['pessoa_id', 'criado_em']);
            $table->index(['ecoponto_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coleta');
    }
};
