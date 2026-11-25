<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * ECOPONTO: ponto de coleta de uma empresa.
 * segredo_qr entra na assinatura do QR da placa (assim o QR não pode ser forjado).
 * O total "coletado" NÃO fica aqui: é calculado a partir das coletas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecoponto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->restrictOnDelete();
            $table->string('codigo', 20)->unique();              // ex.: ECO-01
            $table->string('nome', 150);
            $table->string('descricao', 255)->nullable();
            $table->string('endereco', 255)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('estado', 2)->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->enum('status', ['ativo', 'manutencao', 'inativo'])->default('ativo');
            $table->char('segredo_qr', 64);
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index(['lat', 'lng']);
        });

        Banco::check('ecoponto', 'chk_ecoponto_lat', 'lat BETWEEN -90 AND 90');
        Banco::check('ecoponto', 'chk_ecoponto_lng', 'lng BETWEEN -180 AND 180');
    }

    public function down(): void
    {
        Schema::dropIfExists('ecoponto');
    }
};
