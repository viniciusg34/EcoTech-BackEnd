<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PRODUTO (catálogo da campanha): código de barras -> material e moedas.
 * As colunas off_* guardam o que veio do Open Food Facts (cache),
 * para não consultar a API externa toda vez.
 * Produto que não participa da campanha fica com ativo = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_catalogo', function (Blueprint $table) {
            $table->string('codigo_barras', 14)->primary();
            $table->string('nome', 150);
            $table->foreignId('categoria_id')->nullable()->constrained('categoria_material')->nullOnDelete();
            $table->unsignedInteger('coins')->default(0);
            $table->string('icone', 10)->nullable();
            $table->decimal('peso_kg', 6, 3)->nullable();         // peso estimado da embalagem
            $table->boolean('ativo')->default(true);
            $table->string('off_nome', 200)->nullable();
            $table->string('off_marca', 150)->nullable();
            $table->string('off_imagem_url', 400)->nullable();
            $table->dateTime('off_consultado_em')->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_catalogo');
    }
};
