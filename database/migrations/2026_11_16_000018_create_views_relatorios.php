<?php

use App\Support\Banco;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * VIEWS de relatório (só no MySQL/MariaDB).
 * A API calcula os mesmos números com consultas normais (funciona em qualquer banco);
 * as views ficam para quem quiser consultar direto no banco (phpMyAdmin, DBeaver...).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Banco::ehMysql()) {
            return;
        }

        // kg total, número de coletas e última atividade de cada Ecoponto
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_ecoponto_resumo AS
            SELECT e.id AS ecoponto_id, e.empresa_id, e.codigo, e.nome,
                   COALESCE(SUM(c.total_kg), 0) AS kg_total,
                   COUNT(c.id) AS total_coletas,
                   MAX(c.criado_em) AS ultima_atividade
            FROM ecoponto e
            LEFT JOIN coleta c ON c.ecoponto_id = e.id
            GROUP BY e.id, e.empresa_id, e.codigo, e.nome
        SQL);

        // kg por mês (últimos 6 meses) de cada empresa
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_empresa_kg_mensal AS
            SELECT e.empresa_id, DATE_FORMAT(c.criado_em, '%Y-%m') AS mes, SUM(c.total_kg) AS kg
            FROM coleta c
            JOIN ecoponto e ON e.id = c.ecoponto_id
            WHERE c.criado_em >= DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), INTERVAL 5 MONTH)
            GROUP BY e.empresa_id, DATE_FORMAT(c.criado_em, '%Y-%m')
        SQL);

        // kg por categoria de material, por empresa
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_empresa_categoria AS
            SELECT e.empresa_id, cm.chave AS categoria, cm.rotulo, SUM(ci.peso_kg) AS kg
            FROM coleta_item ci
            JOIN coleta c ON c.id = ci.coleta_id
            JOIN ecoponto e ON e.id = c.ecoponto_id
            JOIN categoria_material cm ON cm.id = ci.categoria_id
            GROUP BY e.empresa_id, cm.chave, cm.rotulo
        SQL);
    }

    public function down(): void
    {
        if (! Banco::ehMysql()) {
            return;
        }
        DB::statement('DROP VIEW IF EXISTS vw_empresa_categoria');
        DB::statement('DROP VIEW IF EXISTS vw_empresa_kg_mensal');
        DB::statement('DROP VIEW IF EXISTS vw_ecoponto_resumo');
    }
};
