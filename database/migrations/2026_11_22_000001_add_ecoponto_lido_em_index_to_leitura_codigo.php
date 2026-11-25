<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Índice para o histórico de leituras da empresa (GET /empresas/me/leituras):
 * filtra por Ecoponto e ordena pela data da leitura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leitura_codigo', function (Blueprint $table) {
            $table->index(['ecoponto_id', 'lido_em']);
        });
    }

    public function down(): void
    {
        Schema::table('leitura_codigo', function (Blueprint $table) {
            $table->dropIndex(['ecoponto_id', 'lido_em']);
        });
    }
};
