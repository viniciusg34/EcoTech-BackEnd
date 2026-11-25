-- =====================================================================
-- EcoTech — 05_testes.sql: testes do banco
-- Rode num banco de TESTE, depois do 01_schema.sql, 02_views_procedures.sql
-- e 03_seed.sql. Cria dados marcados com "@teste-sql.invalid", confere as
-- regras e apaga tudo no fim. O resultado aparece numa tabela:
--   OK      = a regra funcionou
--   FALHOU  = a regra não funcionou (veja a coluna detalhe)
-- Testa: saldo nunca negativo, resgate concorrente, unicidades, CHECKs,
-- anonimização (LGPD) e as views.
-- =====================================================================

SET NAMES utf8mb4;
-- Mesmo modo do Laravel (strict): valor fora da faixa vira erro, não "0"
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TEMPORARY TABLE IF EXISTS resultado_teste;
CREATE TEMPORARY TABLE resultado_teste (
  n       INT AUTO_INCREMENT PRIMARY KEY,
  teste   VARCHAR(120) NOT NULL,
  ok      TINYINT NOT NULL,
  detalhe VARCHAR(255) NULL
);

DROP PROCEDURE IF EXISTS tst_registrar;
DROP PROCEDURE IF EXISTS tst_deve_falhar;
DROP PROCEDURE IF EXISTS tst_limpar;

DELIMITER $$

-- Guarda o resultado de um teste
CREATE PROCEDURE tst_registrar(IN p_teste VARCHAR(120), IN p_ok BOOLEAN, IN p_detalhe VARCHAR(255))
BEGIN
  INSERT INTO resultado_teste (teste, ok, detalhe) VALUES (p_teste, COALESCE(p_ok, 0), p_detalhe);
END$$

-- Executa um comando que PRECISA dar erro. Se p_trecho for informado,
-- a mensagem do erro precisa conter esse texto.
CREATE PROCEDURE tst_deve_falhar(IN p_teste VARCHAR(120), IN p_sql TEXT, IN p_trecho VARCHAR(100))
BEGIN
  DECLARE v_falhou BOOLEAN DEFAULT FALSE;
  DECLARE v_msg TEXT DEFAULT '';
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
    SET v_falhou = TRUE;
  END;

  SET @tst_sql = p_sql;
  PREPARE tst_st FROM @tst_sql;
  EXECUTE tst_st;
  DEALLOCATE PREPARE tst_st;

  CALL tst_registrar(
    p_teste,
    v_falhou AND (p_trecho IS NULL OR v_msg LIKE CONCAT('%', p_trecho, '%')),
    IF(v_falhou, LEFT(CONCAT('erro esperado: ', v_msg), 255), 'o comando NÃO deu erro')
  );
END$$

-- Apaga os dados de teste (de filhos para pais, por causa das FKs)
CREATE PROCEDURE tst_limpar()
BEGIN
  DELETE m FROM movimento_coins m JOIN pessoa p ON p.id = m.pessoa_id
   WHERE p.email LIKE '%@teste-sql.invalid' OR p.email LIKE 'removido-%@teste-sql.invalid';
  DELETE r FROM resgate r JOIN recompensa x ON x.id = r.recompensa_id WHERE x.nome LIKE 'Teste SQL%';
  DELETE l FROM leitura_codigo l JOIN pessoa p ON p.id = l.pessoa_id WHERE p.email LIKE '%@teste-sql.invalid';
  DELETE FROM log_autenticacao WHERE user_agent = 'teste-sql';
  DELETE ec FROM ecoponto_categoria ec JOIN ecoponto e ON e.id = ec.ecoponto_id WHERE e.codigo LIKE 'TSTSQL%';
  DELETE FROM ecoponto WHERE codigo LIKE 'TSTSQL%';
  DELETE FROM empresa WHERE email LIKE '%@teste-sql.invalid';
  DELETE FROM recompensa WHERE nome LIKE 'Teste SQL%';
  DELETE FROM pessoa WHERE email LIKE '%@teste-sql.invalid';
END$$

DELIMITER ;

-- Começa limpo (caso uma execução anterior tenha parado no meio)
CALL tst_limpar();

-- ── Dados de teste ──────────────────────────────────────────────
INSERT INTO pessoa (nome, email, cpf, login, senha_hash, perfil, saldo_coins, ativo) VALUES
  ('Pessoa de Teste SQL Um', 'um@teste-sql.invalid', '00000000191', 'tsqlum', 'x', 'comum', 0, 1),
  ('Pessoa de Teste SQL Dois', 'dois@teste-sql.invalid', NULL, 'tsqldo', 'x', 'comum', 0, 1);
SET @p1 = (SELECT id FROM pessoa WHERE email = 'um@teste-sql.invalid');
SET @p2 = (SELECT id FROM pessoa WHERE email = 'dois@teste-sql.invalid');

INSERT INTO empresa (cnpj, nome_empresa, email, senha_hash, status)
VALUES ('99999999000191', 'Empresa Teste SQL', 'empresa@teste-sql.invalid', 'x', 'ativa');
SET @e1 = LAST_INSERT_ID();

INSERT INTO ecoponto (empresa_id, codigo, nome, lat, lng, status, segredo_qr)
VALUES (@e1, 'TSTSQL-01', 'Ecoponto Teste SQL', -22.9, -43.2, 'ativo', SHA2('teste', 256));
SET @eco1 = LAST_INSERT_ID();

INSERT INTO recompensa (categoria, nome, custo_coins, estoque, ativa) VALUES ('food', 'Teste SQL Café', 80, 5, 1);
SET @r1 = LAST_INSERT_ID();
INSERT INTO recompensa (categoria, nome, custo_coins, estoque, ativa) VALUES ('food', 'Teste SQL Esgotada', 10, 0, 1);
SET @r_sem_estoque = LAST_INSERT_ID();

-- ── 1. Saldo nunca negativo ─────────────────────────────────────
CALL tst_deve_falhar('saldo: UPDATE direto com saldo negativo é recusado',
  CONCAT('UPDATE pessoa SET saldo_coins = -1 WHERE id = ', @p1), NULL);

CALL tst_deve_falhar('saldo: débito maior que o saldo é recusado (sp_creditar_coins)',
  CONCAT('CALL sp_creditar_coins(', @p1, ', -10, ''ajuste'', NULL, NULL, ''Débito de teste'')'), 'saldo_insuficiente');

CALL sp_creditar_coins(@p1, 100, 'ajuste', 'teste', NULL, 'Crédito de teste');
CALL tst_registrar('livro-razão: crédito atualiza o saldo e grava o movimento',
  (SELECT saldo_coins FROM pessoa WHERE id = @p1) = 100
  AND (SELECT SUM(valor) FROM movimento_coins WHERE pessoa_id = @p1) = 100
  AND (SELECT saldo_apos FROM movimento_coins WHERE pessoa_id = @p1 ORDER BY id DESC LIMIT 1) = 100,
  CONCAT('saldo = ', (SELECT saldo_coins FROM pessoa WHERE id = @p1)));

CALL tst_deve_falhar('livro-razão: lançamento de valor zero é recusado',
  CONCAT('CALL sp_creditar_coins(', @p1, ', 0, ''ajuste'', NULL, NULL, ''Zero'')'), 'valor_invalido');

-- ── 2. Resgate (e o que impede o resgate "concorrente") ──────────
-- Saldo 100 e recompensa de 80: o 1º resgate passa, o 2º precisa falhar.
CALL sp_resgatar(@p1, @r1);
CALL tst_deve_falhar('resgate: segundo resgate sem saldo é recusado',
  CONCAT('CALL sp_resgatar(', @p1, ', ', @r1, ')'), 'saldo_insuficiente');
CALL tst_registrar('resgate: só 1 resgate, saldo 20, estoque baixou para 4, débito no livro-razão',
  (SELECT COUNT(*) FROM resgate WHERE pessoa_id = @p1) = 1
  AND (SELECT saldo_coins FROM pessoa WHERE id = @p1) = 20
  AND (SELECT estoque FROM recompensa WHERE id = @r1) = 4
  AND (SELECT SUM(valor) FROM movimento_coins WHERE pessoa_id = @p1) = 20,
  CONCAT('resgates = ', (SELECT COUNT(*) FROM resgate WHERE pessoa_id = @p1), ', saldo = ', (SELECT saldo_coins FROM pessoa WHERE id = @p1)));
CALL tst_registrar('resgate: cupom no formato CUP-XXXXXX',
  (SELECT codigo_cupom REGEXP '^CUP-[0-9A-F]{6}$' FROM resgate WHERE pessoa_id = @p1 LIMIT 1) = 1,
  (SELECT codigo_cupom FROM resgate WHERE pessoa_id = @p1 LIMIT 1));

CALL sp_creditar_coins(@p2, 50, 'ajuste', 'teste', NULL, 'Crédito de teste');
CALL tst_deve_falhar('resgate: recompensa sem estoque é recusada',
  CONCAT('CALL sp_resgatar(', @p2, ', ', @r_sem_estoque, ')'), 'sem_estoque');

-- Concorrência de verdade (dois resgates AO MESMO TEMPO) precisa de duas
-- conexões. Para ver o bloqueio funcionando, abra dois terminais mysql:
--   Terminal A: START TRANSACTION; SELECT saldo_coins FROM pessoa WHERE id = <id> FOR UPDATE;
--   Terminal B: CALL sp_resgatar(<id>, <recompensa>);   -- fica esperando
--   Terminal A: COMMIT;                                  -- B continua e vê o saldo novo
-- A API faz o mesmo (lockForUpdate) e tem um teste automático para isso.

-- ── 3. Unicidades ───────────────────────────────────────────────
CALL tst_deve_falhar('único: e-mail repetido',
  'INSERT INTO pessoa (nome, email, senha_hash) VALUES (''Pessoa Repetida Teste SQL'', ''um@teste-sql.invalid'', ''x'')', 'Duplicate');
CALL tst_deve_falhar('único: CPF repetido',
  'INSERT INTO pessoa (nome, email, cpf, senha_hash) VALUES (''Pessoa Repetida Teste SQL'', ''cpf@teste-sql.invalid'', ''00000000191'', ''x'')', 'Duplicate');
CALL tst_deve_falhar('único: login repetido',
  'INSERT INTO pessoa (nome, email, login, senha_hash) VALUES (''Pessoa Repetida Teste SQL'', ''login@teste-sql.invalid'', ''tsqlum'', ''x'')', 'Duplicate');
CALL tst_deve_falhar('único: CNPJ repetido',
  'INSERT INTO empresa (cnpj, nome_empresa, email, senha_hash) VALUES (''99999999000191'', ''Outra'', ''outra@teste-sql.invalid'', ''x'')', 'Duplicate');
CALL tst_deve_falhar('único: código de Ecoponto repetido',
  CONCAT('INSERT INTO ecoponto (empresa_id, codigo, nome, lat, lng, segredo_qr) VALUES (', @e1, ', ''TSTSQL-01'', ''Outro'', 0, 0, ''x'')'), 'Duplicate');
CALL tst_deve_falhar('único: código de cupom repetido',
  CONCAT('INSERT INTO resgate (pessoa_id, recompensa_id, custo_coins, codigo_cupom) SELECT pessoa_id, recompensa_id, custo_coins, codigo_cupom FROM resgate WHERE pessoa_id = ', @p1, ' LIMIT 1'), 'Duplicate');
INSERT INTO leitura_codigo (pessoa_id, codigo_barras, resultado, coins_creditados, idempotency_key, lido_em)
VALUES (@p1, '7896004004778', 'invalido', 0, '11111111-2222-3333-4444-555555555555', NOW());
CALL tst_deve_falhar('único: Idempotency-Key repetida na leitura',
  CONCAT('INSERT INTO leitura_codigo (pessoa_id, codigo_barras, resultado, coins_creditados, idempotency_key, lido_em) VALUES (', @p1, ', ''7896004004778'', ''invalido'', 0, ''11111111-2222-3333-4444-555555555555'', NOW())'), 'Duplicate');

-- ── 4. Regras CHECK ─────────────────────────────────────────────
CALL tst_deve_falhar('CHECK: recompensa com custo 0',
  'INSERT INTO recompensa (categoria, nome, custo_coins) VALUES (''food'', ''Teste SQL Grátis'', 0)', NULL);
CALL tst_deve_falhar('CHECK: latitude fora de -90..90',
  CONCAT('INSERT INTO ecoponto (empresa_id, codigo, nome, lat, lng, segredo_qr) VALUES (', @e1, ', ''TSTSQL-02'', ''Lat errada'', 95, 0, ''x'')'), NULL);
CALL tst_deve_falhar('CHECK: longitude fora de -180..180',
  CONCAT('INSERT INTO ecoponto (empresa_id, codigo, nome, lat, lng, segredo_qr) VALUES (', @e1, ', ''TSTSQL-03'', ''Lng errada'', 0, 181, ''x'')'), NULL);
CALL tst_deve_falhar('CHECK: movimento de valor 0',
  CONCAT('INSERT INTO movimento_coins (pessoa_id, tipo, valor, saldo_apos, descricao) VALUES (', @p1, ', ''ajuste'', 0, 20, ''Zero'')'), NULL);
CALL tst_deve_falhar('CHECK: log sem pessoa e sem empresa',
  'INSERT INTO log_autenticacao (evento, data_hora, sucesso, user_agent) VALUES (''senha'', NOW(), 0, ''teste-sql'')', NULL);
CALL tst_deve_falhar('CHECK: log com pessoa E empresa',
  CONCAT('INSERT INTO log_autenticacao (pessoa_id, empresa_id, evento, data_hora, sucesso, user_agent) VALUES (', @p1, ', ', @e1, ', ''senha'', NOW(), 0, ''teste-sql'')'), NULL);

-- ── 5. Anonimização (LGPD): os dados pessoais somem, o histórico fica ──
SET @mov_antes = (SELECT COUNT(*) FROM movimento_coins WHERE pessoa_id = @p1);
UPDATE pessoa SET
  nome = 'Pessoa removida', email = CONCAT('removido-', id, '@teste-sql.invalid'), cpf = NULL, login = NULL,
  nome_mae = NULL, nascimento = NULL, sexo = NULL, telefone_celular = NULL, telefone_fixo = NULL, cep = NULL,
  logradouro = NULL, numero = NULL, bairro = NULL, cidade = NULL, uf = NULL, avatar_url = NULL,
  ativo = 0, anonimizado_em = NOW()
WHERE id = @p1;
CALL tst_registrar('LGPD: anonimizar apaga os dados pessoais e mantém movimentos e resgates',
  (SELECT cpf IS NULL AND login IS NULL AND ativo = 0 AND anonimizado_em IS NOT NULL FROM pessoa WHERE id = @p1) = 1
  AND (SELECT COUNT(*) FROM movimento_coins WHERE pessoa_id = @p1) = @mov_antes
  AND (SELECT COUNT(*) FROM resgate WHERE pessoa_id = @p1) = 1,
  CONCAT('movimentos: ', @mov_antes));
CALL tst_deve_falhar('LGPD: DELETE de pessoa com histórico é bloqueado (FK RESTRICT; use anonimização)',
  CONCAT('DELETE FROM pessoa WHERE id = ', @p1), 'foreign key');

-- ── 6. Views ────────────────────────────────────────────────────
CALL tst_registrar('views: vw_ecoponto_resumo tem uma linha por Ecoponto',
  (SELECT COUNT(*) FROM vw_ecoponto_resumo) = (SELECT COUNT(*) FROM ecoponto),
  CONCAT((SELECT COUNT(*) FROM vw_ecoponto_resumo), ' linhas'));
CALL tst_registrar('views: vw_empresa_kg_mensal e vw_empresa_categoria respondem',
  (SELECT COUNT(*) >= 0 FROM vw_empresa_kg_mensal) AND (SELECT COUNT(*) >= 0 FROM vw_empresa_categoria), NULL);

-- ── Resultado ───────────────────────────────────────────────────
SELECT n, IF(ok, 'OK', 'FALHOU') AS resultado, teste, detalhe FROM resultado_teste ORDER BY n;
SELECT SUM(ok) AS passaram, COUNT(*) - SUM(ok) AS falharam FROM resultado_teste;

-- Limpeza
CALL tst_limpar();
DROP PROCEDURE IF EXISTS tst_registrar;
DROP PROCEDURE IF EXISTS tst_deve_falhar;
DROP PROCEDURE IF EXISTS tst_limpar;
