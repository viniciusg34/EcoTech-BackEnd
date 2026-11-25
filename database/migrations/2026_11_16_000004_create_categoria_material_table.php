<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CATEGORIA DE MATERIAL: metal, papel, vidro, plástico, eletrônicos, madeira.
 * coins_por_unidade = quantas moedas valem 1 kg (ou 1 unidade, se unidade = 'un').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_material', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 20)->unique();
            $table->string('rotulo', 50);
            $table->char('cor_hex', 7);
            $table->unsignedInteger('coins_por_unidade');
            $table->enum('unidade', ['kg', 'un']);
            $table->string('icone', 10)->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_material');
    }
};
