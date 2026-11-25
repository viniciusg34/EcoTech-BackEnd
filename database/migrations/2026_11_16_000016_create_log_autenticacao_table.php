<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * LOG DE AUTENTICAÇÃO: cada login, 2FA (certo ou errado), login com Google e recuperação de senha.
 * Exatamente um entre pessoa_id e empresa_id fica preenchido.
 * É a base da tela de LOG do master (enunciado do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_autenticacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoa')->restrictOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresa')->restrictOnDelete();
            $table->string('evento', 30);                     // senha, 2fa, google, recuperacao, redefinir_senha
            $table->dateTime('data_hora');
            $table->string('metodo_2fa', 60)->nullable();      // texto da pergunta usada
            $table->boolean('sucesso');
            $table->unsignedTinyInteger('tentativa_n')->nullable();
            $table->binary('ip', 16)->nullable();             // IPv4 ou IPv6 em binário
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->dateTime('atualizado_em')->useCurrent()->useCurrentOnUpdate();

            $table->index('data_hora');
        });

        Banco::check(
            'log_autenticacao',
            'chk_log_uma_conta',
            '(pessoa_id IS NULL AND empresa_id IS NOT NULL) OR (pessoa_id IS NOT NULL AND empresa_id IS NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('log_autenticacao');
    }
};
