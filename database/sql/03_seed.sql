-- =====================================================================
-- EcoTech — 03_seed.sql: dados iniciais
-- MySQL 8 (InnoDB, utf8mb4). Pode rodar mais de uma vez.
-- Gerado a partir das migrations do Laravel (que são a fonte da verdade):
-- se mudar uma migration, atualize este arquivo também.
-- =====================================================================

-- Rode depois do 01_schema.sql. Não apaga nada: insere o que falta e atualiza
-- o que já existe (pela chave natural: chave, código, CNPJ, e-mail).
--
-- MASTER: o enunciado pede que o usuário master seja criado por script SQL.
-- Este arquivo já traz o hash (bcrypt) da senha inicial do master:
--   login: MASTER (ou master@ecotech.com)   senha inicial: Master123
--   2FA: nome da mãe "Maria da Silva", nascimento 01/01/2000, CEP 20040-020
-- O master só é criado se ainda não existir: rodar de novo NÃO volta a senha.
-- TROQUE a senha antes de publicar a API (php artisan ecotech:criar-master
-- com MASTER_PASSWORD no .env), porque este arquivo fica no GitHub.
--
-- Empresas de exemplo: preencha @empresa_senha_hash se quiser entrar com elas:
--   php -r "echo password_hash('SuaSenhaAqui', PASSWORD_BCRYPT), PHP_EOL;"
-- Sem ele, as empresas ficam com uma senha impossível (ninguém consegue entrar).
-- Com a API, o normal é usar: php artisan migrate --seed (lê as senhas do .env).

SET NAMES utf8mb4;
SET @master_senha_hash  = '$2y$12$d8ZL5gGT/UujQmPGVPkZweoco7lc9prsAClZBFzPO.K/o.1IDAixO';  -- senha inicial: Master123
SET @empresa_senha_hash = NULL;  -- ex.: '$2y$12$....'
SET @senha_impossivel   = CONCAT('!sem-senha-', SHA2(UUID(), 256));

-- configuracao (valores padrão das regras de negócio)
INSERT INTO configuracao (chave, valor, descricao, publico) VALUES
  ('checkin_coins', '50', 'Moedas ganhas em cada check-in', 1),
  ('checkin_cooldown_horas', '6', 'Horas de espera entre check-ins no mesmo Ecoponto', 1),
  ('checkin_kg', '0.5', 'Kg contados em cada check-in', 1),
  ('duplicidade_horas', '24', 'Horas em que o mesmo produto não pode ser lido de novo pela mesma pessoa', 1),
  ('sessao_minutos', '10', 'Minutos que a sessão no Ecoponto fica aberta', 1)
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), publico = VALUES(publico);

-- categoria_material (6 materiais, cores do front)
INSERT INTO categoria_material (chave, rotulo, cor_hex, coins_por_unidade, unidade, icone) VALUES
  ('metal', 'Metal', '#f9a825', 20, 'kg', '🥫'),
  ('papel', 'Papel', '#66bb6a', 8, 'kg', '📦'),
  ('vidro', 'Vidro', '#42a5f5', 6, 'kg', '🫙'),
  ('plastico', 'Plástico', '#ef5350', 12, 'kg', '🧴'),
  ('eletronicos', 'Eletrônicos', '#ab47bc', 25, 'un', '🔋'),
  ('madeira', 'Madeira', '#8d6e63', 5, 'kg', '🪵')
ON DUPLICATE KEY UPDATE rotulo = VALUES(rotulo), cor_hex = VALUES(cor_hex), coins_por_unidade = VALUES(coins_por_unidade),
  unidade = VALUES(unidade), icone = VALUES(icone);

-- recompensa (11 recompensas do front; não há chave natural, então insere só se o nome não existir)
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'food', '🍔', 'Cupom McDonald''s', '20% OFF em combos', 200, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Cupom McDonald''s');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'food', '☕', 'Café Grátis', 'Starbucks ou cafeteria parceira', 80, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Café Grátis');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'food', '🥤', 'Eco Garrafa', 'Garrafa reutilizável em cafeteria parceira', 120, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Eco Garrafa');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'transport', '🎫', 'Passe Metrô Rio', '1 viagem gratuita', 150, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Passe Metrô Rio');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'transport', '🚲', 'Bike Itaú 1h', '1 hora de uso grátis', 90, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Bike Itaú 1h');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'health', '🌱', 'Kit Sementes', 'Sementes de árvores nativas', 100, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Kit Sementes');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'health', '💊', 'Desconto Farmácia', '15% OFF em medicamentos', 120, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Desconto Farmácia');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'shop', '🛍️', 'Bolsa Ecotech', 'Sacola sustentável da Ecotech', 180, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Bolsa Ecotech');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'shop', '🛍️', 'Vale Compras R$30', 'Lojas parceiras EcoTech', 300, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Vale Compras R$30');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'shop', '👕', 'Camiseta Sustentável', 'Algodão orgânico reciclado', 220, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Camiseta Sustentável');
INSERT INTO recompensa (categoria, icone, nome, descricao, custo_coins, estoque, ativa)
SELECT 'leisure', '🎭', 'Ingresso de Cinema', 'Meia-entrada em qualquer sessão', 250, NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM recompensa WHERE nome = 'Ingresso de Cinema');

-- produto_catalogo (6 produtos; códigos EAN-13 com o dígito verificador corrigido)
INSERT INTO produto_catalogo (codigo_barras, nome, categoria_id, coins, icone, peso_kg, ativo)
SELECT v.codigo_barras, v.nome, c.id, v.coins, v.icone, v.peso_kg, v.ativo
FROM (
  SELECT '7896004004778' AS codigo_barras, 'Garrafa PET 500ml' AS nome, 'plastico' AS chave, 30 AS coins, '🧴' AS icone, 0.02 AS peso_kg, 1 AS ativo
  UNION ALL SELECT '7891234567895' AS codigo_barras, 'Lata de Alumínio 350ml' AS nome, 'metal' AS chave, 40 AS coins, '🥫' AS icone, 0.02 AS peso_kg, 1 AS ativo
  UNION ALL SELECT '7896666000019' AS codigo_barras, 'Caixa de Papelão' AS nome, 'papel' AS chave, 20 AS coins, '📦' AS icone, 0.2 AS peso_kg, 1 AS ativo
  UNION ALL SELECT '7899000001239' AS codigo_barras, 'Vidro de Conserva' AS nome, 'vidro' AS chave, 35 AS coins, '🫙' AS icone, 0.3 AS peso_kg, 1 AS ativo
  UNION ALL SELECT '7891910000197' AS codigo_barras, 'Pilha Alcalina AA' AS nome, 'eletronicos' AS chave, 80 AS coins, '🔋' AS icone, 0.02 AS peso_kg, 1 AS ativo
  UNION ALL SELECT '7896000000002' AS codigo_barras, 'Embalagem Tetra Pak' AS nome, 'papel' AS chave, 25 AS coins, '🧃' AS icone, 0.03 AS peso_kg, 1 AS ativo
) v JOIN categoria_material c ON c.chave = v.chave
ON DUPLICATE KEY UPDATE nome = VALUES(nome), categoria_id = VALUES(categoria_id), coins = VALUES(coins), icone = VALUES(icone), peso_kg = VALUES(peso_kg);

-- empresa (3 empresas FICTÍCIAS donas dos Ecopontos de exemplo)
INSERT INTO empresa (cnpj, nome_empresa, razao_social, email, senha_hash, endereco, status) VALUES
  ('11222333000181', 'Recicla Costa Verde', 'Recicla Costa Verde Ltda (fictícia)', 'contato@costaverde.ecotech.example', COALESCE(@empresa_senha_hash, @senha_impossivel), 'Mangaratiba - RJ', 'ativa'),
  ('44555666000181', 'Rio Recicla Soluções Ambientais', 'Rio Recicla Soluções Ambientais Ltda (fictícia)', 'contato@riorecicla.ecotech.example', COALESCE(@empresa_senha_hash, @senha_impossivel), 'Rio de Janeiro - RJ', 'ativa'),
  ('77888999000181', 'Metropolitana Verde Reciclagem', 'Metropolitana Verde Reciclagem Ltda (fictícia)', 'contato@metroverde.ecotech.example', COALESCE(@empresa_senha_hash, @senha_impossivel), 'Niterói - RJ', 'ativa')
ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), razao_social = VALUES(razao_social), endereco = VALUES(endereco);

-- ecoponto (ECO-01 a ECO-22, coordenadas do js/data-map-points.js). O segredo do QR é sorteado aqui e não muda depois.
INSERT INTO ecoponto (empresa_id, codigo, nome, descricao, endereco, cidade, estado, lat, lng, status, segredo_qr)
SELECT em.id, v.codigo, v.nome, v.descricao, v.endereco, v.cidade, v.estado, v.lat, v.lng, v.status, SHA2(CONCAT(UUID(), RAND(), v.codigo), 256)
FROM (
  SELECT 'ECO-01' AS codigo, 'Centro Recicla Mangaratiba' AS nome, 'Papelão, caixotes e restos de madeira' AS descricao, 'Centro' AS endereco, 'Mangaratiba' AS cidade, 'RJ' AS estado, -22.9597 AS lat, -44.0406 AS lng, 'ativo' AS status, '11222333000181' AS cnpj
  UNION ALL SELECT 'ECO-02' AS codigo, 'EcoPonto Vila Muriqui' AS nome, 'Latinhas, ferragens e embalagens plásticas' AS descricao, 'Vila Muriqui' AS endereco, 'Mangaratiba' AS cidade, 'RJ' AS estado, -22.9245 AS lat, -43.9538 AS lng, 'ativo' AS status, '11222333000181' AS cnpj
  UNION ALL SELECT 'ECO-03' AS codigo, 'Itacuruçá Plásticos' AS nome, 'Garrafas PET, tampinhas e potes de vidro' AS descricao, 'Itacuruçá' AS endereco, 'Mangaratiba' AS cidade, 'RJ' AS estado, -22.9291 AS lat, -43.886 AS lng, 'ativo' AS status, '11222333000181' AS cnpj
  UNION ALL SELECT 'ECO-04' AS codigo, 'Jacareí Vidros' AS nome, 'Potes de vidro e sucata metálica' AS descricao, 'Conceição de Jacareí' AS endereco, 'Mangaratiba' AS cidade, 'RJ' AS estado, -23.0188 AS lat, -44.2081 AS lng, 'ativo' AS status, '11222333000181' AS cnpj
  UNION ALL SELECT 'ECO-05' AS codigo, 'EcoCentro Metal' AS nome, 'Sucata de metal e paletes de madeira' AS descricao, 'Centro' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9068 AS lat, -43.1729 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-06' AS codigo, 'Vidro Lapa' AS nome, 'Garrafas de vidro e papelão' AS descricao, 'Lapa' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9122 AS lat, -43.1769 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-07' AS codigo, 'EcoPapel Botafogo' AS nome, 'Papel, móveis quebrados e embalagens plásticas' AS descricao, 'Botafogo' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9519 AS lat, -43.2105 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-08' AS codigo, 'TechRecicla Copacabana' AS nome, 'Eletrônicos, pilhas e sucata metálica' AS descricao, 'Copacabana' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9711 AS lat, -43.1823 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-09' AS codigo, 'Ipanema Plásticos' AS nome, 'PET, sacolas e frascos de vidro' AS descricao, 'Ipanema' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9836 AS lat, -43.2044 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-10' AS codigo, 'Tijuca Sustentável' AS nome, 'Jornais, caixas e restos de obra em madeira' AS descricao, 'Tijuca' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9329 AS lat, -43.2372 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-11' AS codigo, 'Méier Metais' AS nome, 'Sucata metálica e eletrônicos' AS descricao, 'Méier' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9015 AS lat, -43.2801 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-12' AS codigo, 'Madureira Vidros' AS nome, 'Vidros e embalagens plásticas' AS descricao, 'Madureira' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.876 AS lat, -43.3364 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-13' AS codigo, 'Ilha Eco-Tech' AS nome, 'Eletrônicos, metais e plásticos' AS descricao, 'Ilha do Governador' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.8122 AS lat, -43.2144 AS lng, 'ativo' AS status, '44555666000181' AS cnpj
  UNION ALL SELECT 'ECO-14' AS codigo, 'Barra PET Coleta' AS nome, 'Garrafas PET e papel' AS descricao, 'Barra da Tijuca' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -23.0003 AS lat, -43.3658 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-15' AS codigo, 'Recreio Vidros' AS nome, 'Vidros e madeira' AS descricao, 'Recreio dos Bandeirantes' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -23.0181 AS lat, -43.4682 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-16' AS codigo, 'Bangu Eletrônicos' AS nome, 'Eletrônicos e metais' AS descricao, 'Bangu' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.8752 AS lat, -43.465 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-17' AS codigo, 'Campo Grande Plast' AS nome, 'Plásticos e vidros' AS descricao, 'Campo Grande' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.8986 AS lat, -43.5598 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-18' AS codigo, 'Jacarepaguá Papéis' AS nome, 'Papel, madeira e metal' AS descricao, 'Jacarepaguá' AS endereco, 'Rio de Janeiro' AS cidade, 'RJ' AS estado, -22.9678 AS lat, -43.3888 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-19' AS codigo, 'Icaraí Metais (Niterói)' AS nome, 'Metais e vidros' AS descricao, 'Icaraí' AS endereco, 'Niterói' AS cidade, 'RJ' AS estado, -22.9056 AS lat, -43.1065 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-20' AS codigo, 'São Gonçalo Plásticos' AS nome, 'Plásticos e papel' AS descricao, 'Centro' AS endereco, 'São Gonçalo' AS cidade, 'RJ' AS estado, -22.8269 AS lat, -43.0538 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-21' AS codigo, 'Nova Iguaçu Vidros' AS nome, 'Vidros, metais e madeira' AS descricao, 'Centro' AS endereco, 'Nova Iguaçu' AS cidade, 'RJ' AS estado, -22.7562 AS lat, -43.4607 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
  UNION ALL SELECT 'ECO-22' AS codigo, 'Duque de Caxias Papel' AS nome, 'Papel e plásticos' AS descricao, 'Centro' AS endereco, 'Duque de Caxias' AS cidade, 'RJ' AS estado, -22.7856 AS lat, -43.3117 AS lng, 'ativo' AS status, '77888999000181' AS cnpj
) v JOIN empresa em ON em.cnpj = v.cnpj
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), endereco = VALUES(endereco), cidade = VALUES(cidade),
  estado = VALUES(estado), lat = VALUES(lat), lng = VALUES(lng);

-- ecoponto_categoria (materiais aceitos em cada Ecoponto)
INSERT IGNORE INTO ecoponto_categoria (ecoponto_id, categoria_id)
SELECT e.id, c.id
FROM (
  SELECT 'ECO-01' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-01' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-02' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-02' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-03' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-03' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-04' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-04' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-05' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-05' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-06' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-06' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-07' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-07' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-07' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-08' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-08' AS codigo, 'eletronicos' AS chave
  UNION ALL SELECT 'ECO-09' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-09' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-10' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-10' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-11' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-11' AS codigo, 'eletronicos' AS chave
  UNION ALL SELECT 'ECO-12' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-12' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-13' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-13' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-13' AS codigo, 'eletronicos' AS chave
  UNION ALL SELECT 'ECO-14' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-14' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-15' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-15' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-16' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-16' AS codigo, 'eletronicos' AS chave
  UNION ALL SELECT 'ECO-17' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-17' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-18' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-18' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-18' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-19' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-19' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-20' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-20' AS codigo, 'plastico' AS chave
  UNION ALL SELECT 'ECO-21' AS codigo, 'metal' AS chave
  UNION ALL SELECT 'ECO-21' AS codigo, 'vidro' AS chave
  UNION ALL SELECT 'ECO-21' AS codigo, 'madeira' AS chave
  UNION ALL SELECT 'ECO-22' AS codigo, 'papel' AS chave
  UNION ALL SELECT 'ECO-22' AS codigo, 'plastico' AS chave
) v
JOIN ecoponto e ON e.codigo = v.codigo
JOIN categoria_material c ON c.chave = v.chave;

-- pessoa master (login MASTER, guardado em minúsculas; master@ecotech.com), criada pelo script como pede o enunciado.
-- Os dados da pergunta de segurança (nome da mãe, nascimento, CEP) são os padrões do .env.example: troque depois.
-- Se o master já existe, nada muda (a senha atual dele continua valendo).
INSERT INTO pessoa (nome, email, cpf, login, senha_hash, nome_mae, nascimento, sexo, cep, perfil, saldo_coins, termos_aceitos_em, ativo)
SELECT 'Administrador Master EcoTech', 'master@ecotech.com', '52998224725', 'master', @master_senha_hash,
       'Maria da Silva', '2000-01-01', 'nao_informar', '20040020', 'master', 0, NOW(), 1
FROM DUAL
WHERE @master_senha_hash IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM pessoa WHERE email = 'master@ecotech.com' OR cpf = '52998224725' OR login = 'master');
