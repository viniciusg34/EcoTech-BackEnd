-- =====================================================================
-- EcoTech — 02_views_procedures.sql: views dos painéis e procedures do livro-razão
-- MySQL 8 (InnoDB, utf8mb4). Pode rodar mais de uma vez.
-- Gerado a partir das migrations do Laravel (que são a fonte da verdade):
-- se mudar uma migration, atualize este arquivo também.
-- =====================================================================
-- Rode depois do 01_schema.sql. Use um cliente que entenda DELIMITER
-- (mysql na linha de comando, phpMyAdmin, MySQL Workbench, DBeaver em modo script).

-- Views (as mesmas que a migration 2026_11_16_000018 cria)
-- vw_ecoponto_resumo: kg total, nº de coletas e última atividade de cada Ecoponto
-- vw_empresa_kg_mensal: kg por mês (últimos 6 meses) de cada empresa
-- vw_empresa_categoria: kg por categoria de material de cada empresa

CREATE OR REPLACE VIEW vw_ecoponto_resumo AS
SELECT e.id AS ecoponto_id, e.empresa_id, e.codigo, e.nome,
       COALESCE(SUM(c.total_kg), 0) AS kg_total,
       COUNT(c.id) AS total_coletas,
       MAX(c.criado_em) AS ultima_atividade
FROM ecoponto e
LEFT JOIN coleta c ON c.ecoponto_id = e.id
GROUP BY e.id, e.empresa_id, e.codigo, e.nome;

CREATE OR REPLACE VIEW vw_empresa_kg_mensal AS
SELECT e.empresa_id, DATE_FORMAT(c.criado_em, '%Y-%m') AS mes, SUM(c.total_kg) AS kg
FROM coleta c
JOIN ecoponto e ON e.id = c.ecoponto_id
WHERE c.criado_em >= DATE_SUB(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), INTERVAL 5 MONTH)
GROUP BY e.empresa_id, DATE_FORMAT(c.criado_em, '%Y-%m');

CREATE OR REPLACE VIEW vw_empresa_categoria AS
SELECT e.empresa_id, cm.chave AS categoria, cm.rotulo, SUM(ci.peso_kg) AS kg
FROM coleta_item ci
JOIN coleta c ON c.id = ci.coleta_id
JOIN ecoponto e ON e.id = c.ecoponto_id
JOIN categoria_material cm ON cm.id = ci.categoria_id
GROUP BY e.empresa_id, cm.chave, cm.rotulo;

-- ---------------------------------------------------------------------
-- sp_creditar_coins: lança um crédito (valor > 0) ou débito (valor < 0)
-- no livro-razão. Trava a linha da pessoa (FOR UPDATE), então dois
-- lançamentos ao mesmo tempo esperam um pelo outro e o saldo nunca fica
-- negativo. A API faz o mesmo no CoinService (DB::transaction + lockForUpdate).
-- Uso: CALL sp_creditar_coins(1, 50, 'checkin', 'coleta', 10, 'Check-in no ECO-01');
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_creditar_coins;
DELIMITER $$
CREATE PROCEDURE sp_creditar_coins(
    IN p_pessoa_id   BIGINT UNSIGNED,
    IN p_valor       INT,
    IN p_tipo        VARCHAR(10),
    IN p_origem_tipo VARCHAR(30),
    IN p_origem_id   BIGINT UNSIGNED,
    IN p_descricao   VARCHAR(255)
)
BEGIN
    DECLARE v_saldo BIGINT DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_valor IS NULL OR p_valor = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'valor_invalido';
    END IF;

    START TRANSACTION;

    SELECT saldo_coins INTO v_saldo FROM pessoa WHERE id = p_pessoa_id FOR UPDATE;
    IF v_saldo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'pessoa_nao_encontrada';
    END IF;
    IF v_saldo + p_valor < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo_insuficiente';
    END IF;

    UPDATE pessoa SET saldo_coins = v_saldo + p_valor WHERE id = p_pessoa_id;
    INSERT INTO movimento_coins (pessoa_id, tipo, valor, saldo_apos, origem_tipo, origem_id, descricao)
    VALUES (p_pessoa_id, p_tipo, p_valor, v_saldo + p_valor, p_origem_tipo, p_origem_id, p_descricao);

    COMMIT;
    SELECT LAST_INSERT_ID() AS movimento_id, v_saldo + p_valor AS saldo;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- sp_resgatar: troca moedas por uma recompensa. Trava a pessoa e a
-- recompensa, confere ativa/estoque/saldo, cria o resgate com o cupom,
-- baixa o estoque e lança o débito no livro-razão, tudo ou nada.
-- Uso: CALL sp_resgatar(1, 2);
-- Erros (SQLSTATE 45000): pessoa_nao_encontrada, recompensa_indisponivel,
-- sem_estoque, saldo_insuficiente.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_resgatar;
DELIMITER $$
CREATE PROCEDURE sp_resgatar(
    IN p_pessoa_id     BIGINT UNSIGNED,
    IN p_recompensa_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_saldo   BIGINT DEFAULT NULL;
    DECLARE v_custo   INT DEFAULT NULL;
    DECLARE v_estoque INT DEFAULT NULL;
    DECLARE v_ativa   TINYINT DEFAULT 0;
    DECLARE v_nome    VARCHAR(150);
    DECLARE v_icone   VARCHAR(10);
    DECLARE v_cupom   VARCHAR(20);
    DECLARE v_resgate BIGINT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT saldo_coins INTO v_saldo FROM pessoa WHERE id = p_pessoa_id AND ativo = 1 FOR UPDATE;
    IF v_saldo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'pessoa_nao_encontrada';
    END IF;

    SELECT custo_coins, estoque, ativa, nome, icone
      INTO v_custo, v_estoque, v_ativa, v_nome, v_icone
      FROM recompensa WHERE id = p_recompensa_id FOR UPDATE;
    IF v_custo IS NULL OR v_ativa = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'recompensa_indisponivel';
    END IF;
    IF v_estoque IS NOT NULL AND v_estoque <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sem_estoque';
    END IF;
    IF v_saldo < v_custo THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo_insuficiente';
    END IF;

    SET v_cupom = CONCAT('CUP-', UPPER(SUBSTRING(SHA2(CONCAT(UUID(), RAND()), 256), 1, 6)));
    INSERT INTO resgate (pessoa_id, recompensa_id, custo_coins, codigo_cupom, status)
    VALUES (p_pessoa_id, p_recompensa_id, v_custo, v_cupom, 'emitido');
    SET v_resgate = LAST_INSERT_ID();

    IF v_estoque IS NOT NULL THEN
        UPDATE recompensa SET estoque = estoque - 1 WHERE id = p_recompensa_id;
    END IF;

    UPDATE pessoa SET saldo_coins = v_saldo - v_custo WHERE id = p_pessoa_id;
    INSERT INTO movimento_coins (pessoa_id, tipo, valor, saldo_apos, origem_tipo, origem_id, descricao, subtitulo, icone)
    VALUES (p_pessoa_id, 'resgate', -v_custo, v_saldo - v_custo, 'resgate', v_resgate, v_nome, CONCAT('Cupom ', v_cupom), v_icone);

    COMMIT;
    SELECT v_resgate AS resgate_id, v_cupom AS codigo_cupom, v_saldo - v_custo AS saldo;
END$$
DELIMITER ;
