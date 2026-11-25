<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ITEM DA COLETA: cada material entregue.
 * quantidade = na unidade da categoria (kg ou unidades).
 * peso_kg = peso estimado em kg (para as estatísticas, inclusive de itens contados em unidades).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coleta_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coleta_id')->constrained('coleta')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('categoria_material')->restrictOnDelete();
            $table->string('produto_codigo', 14)->nullable();
            $table->decimal('quantidade', 10, 2);
            $table->decimal('peso_kg', 10, 3)->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('produto_codigo')->references('codigo_barras')->on('produto_catalogo')->nullOnDelete();
        });

        Banco::check('coleta_item', 'chk_coleta_item_quantidade', 'quantidade > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('coleta_item');
    }
};
