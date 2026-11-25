-- =====================================================================
-- EcoTech — 01_schema.sql: tabelas, chaves, índices e regras CHECK
-- MySQL 8 (InnoDB, utf8mb4). Pode rodar mais de uma vez.
-- Gerado a partir das migrations do Laravel (que são a fonte da verdade):
-- se mudar uma migration, atualize este arquivo também.
-- =====================================================================

-- Para criar um banco separado (recomendado), descomente:
-- CREATE DATABASE IF NOT EXISTS ecotech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE ecotech;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- configuracao  (migration 2026_11_16_000001_create_configuracao_table.php)
CREATE TABLE IF NOT EXISTS `configuracao` (
  `chave` varchar(50) not null,
  `valor` varchar(255) not null,
  `descricao` varchar(255) null,
  `publico` tinyint(1) not null default '0',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pessoa  (migration 2026_11_16_000002_create_pessoa_table.php)
CREATE TABLE IF NOT EXISTS `pessoa` (
  `id` bigint unsigned not null auto_increment,
  `nome` varchar(80) not null,
  `email` varchar(255) not null,
  `cpf` char(11) null,
  `login` char(6) null,
  `senha_hash` varchar(255) not null,
  `nome_mae` varchar(150) null,
  `nascimento` date null,
  `sexo` enum('feminino', 'masculino', 'outro', 'nao_informar') null,
  `telefone_celular` varchar(11) null,
  `telefone_fixo` varchar(10) null,
  `cep` char(8) null,
  `logradouro` varchar(150) null,
  `numero` varchar(20) null,
  `bairro` varchar(100) null,
  `cidade` varchar(100) null,
  `uf` char(2) null,
  `perfil` enum('comum', 'master') not null default 'comum',
  `avatar_url` varchar(400) null,
  `saldo_coins` bigint unsigned not null default '0',
  `nivel_visto` varchar(30) null,
  `preferencias` json null,
  `termos_aceitos_em` datetime null,
  `ativo` tinyint(1) not null default '1',
  `anonimizado_em` datetime null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pessoa_email_unique` (`email`),
  UNIQUE KEY `pessoa_cpf_unique` (`cpf`),
  UNIQUE KEY `pessoa_login_unique` (`login`),
  CONSTRAINT `chk_pessoa_saldo` CHECK (saldo_coins >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- empresa  (migration 2026_11_16_000003_create_empresa_table.php)
CREATE TABLE IF NOT EXISTS `empresa` (
  `id` bigint unsigned not null auto_increment,
  `cnpj` char(14) not null,
  `nome_empresa` varchar(150) not null,
  `razao_social` varchar(150) null,
  `email` varchar(255) not null,
  `senha_hash` varchar(255) not null,
  `endereco` varchar(255) null,
  `status` enum('ativa', 'suspensa') not null default 'ativa',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_cnpj_unique` (`cnpj`),
  UNIQUE KEY `empresa_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- categoria_material  (migration 2026_11_16_000004_create_categoria_material_table.php)
CREATE TABLE IF NOT EXISTS `categoria_material` (
  `id` bigint unsigned not null auto_increment,
  `chave` varchar(20) not null,
  `rotulo` varchar(50) not null,
  `cor_hex` char(7) not null,
  `coins_por_unidade` int unsigned not null,
  `unidade` enum('kg', 'un') not null,
  `icone` varchar(10) null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoria_material_chave_unique` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ecoponto  (migration 2026_11_16_000005_create_ecoponto_table.php)
CREATE TABLE IF NOT EXISTS `ecoponto` (
  `id` bigint unsigned not null auto_increment,
  `empresa_id` bigint unsigned not null,
  `codigo` varchar(20) not null,
  `nome` varchar(150) not null,
  `descricao` varchar(255) null,
  `endereco` varchar(255) null,
  `cidade` varchar(100) null,
  `estado` varchar(2) null,
  `lat` decimal(9, 6) not null,
  `lng` decimal(9, 6) not null,
  `status` enum('ativo', 'manutencao', 'inativo') not null default 'ativo',
  `segredo_qr` char(64) not null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ecoponto_codigo_unique` (`codigo`),
  KEY `ecoponto_lat_lng_index` (`lat`, `lng`),
  CONSTRAINT `ecoponto_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_ecoponto_lat` CHECK (lat BETWEEN -90 AND 90),
  CONSTRAINT `chk_ecoponto_lng` CHECK (lng BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ecoponto_categoria  (migration 2026_11_16_000006_create_ecoponto_categoria_table.php)
CREATE TABLE IF NOT EXISTS `ecoponto_categoria` (
  `ecoponto_id` bigint unsigned not null,
  `categoria_id` bigint unsigned not null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`ecoponto_id`, `categoria_id`),
  CONSTRAINT `ecoponto_categoria_ecoponto_id_foreign` FOREIGN KEY (`ecoponto_id`) REFERENCES `ecoponto` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ecoponto_categoria_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_material` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sessao_ecoponto  (migration 2026_11_16_000007_create_sessao_ecoponto_table.php)
CREATE TABLE IF NOT EXISTS `sessao_ecoponto` (
  `id` bigint unsigned not null auto_increment,
  `ecoponto_id` bigint unsigned not null,
  `pessoa_id` bigint unsigned not null,
  `token` char(64) not null,
  `status` enum('ativa', 'encerrada', 'expirada') not null default 'ativa',
  `iniciada_em` datetime not null,
  `expira_em` datetime not null,
  `encerrada_em` datetime null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sessao_ecoponto_token_unique` (`token`),
  KEY `sessao_ecoponto_ecoponto_id_status_index` (`ecoponto_id`, `status`),
  KEY `sessao_ecoponto_pessoa_id_status_index` (`pessoa_id`, `status`),
  CONSTRAINT `sessao_ecoponto_ecoponto_id_foreign` FOREIGN KEY (`ecoponto_id`) REFERENCES `ecoponto` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sessao_ecoponto_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- coleta  (migration 2026_11_16_000008_create_coleta_table.php)
CREATE TABLE IF NOT EXISTS `coleta` (
  `id` bigint unsigned not null auto_increment,
  `pessoa_id` bigint unsigned not null,
  `ecoponto_id` bigint unsigned not null,
  `sessao_id` bigint unsigned null,
  `tipo` enum('checkin', 'entrega') not null,
  `total_coins` int unsigned not null default '0',
  `total_kg` decimal(10, 2) not null default '0',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coleta_pessoa_id_criado_em_index` (`pessoa_id`, `criado_em`),
  KEY `coleta_ecoponto_id_criado_em_index` (`ecoponto_id`, `criado_em`),
  CONSTRAINT `coleta_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `coleta_ecoponto_id_foreign` FOREIGN KEY (`ecoponto_id`) REFERENCES `ecoponto` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `coleta_sessao_id_foreign` FOREIGN KEY (`sessao_id`) REFERENCES `sessao_ecoponto` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- produto_catalogo  (migration 2026_11_16_000009_create_produto_catalogo_table.php)
CREATE TABLE IF NOT EXISTS `produto_catalogo` (
  `codigo_barras` varchar(14) not null,
  `nome` varchar(150) not null,
  `categoria_id` bigint unsigned null,
  `coins` int unsigned not null default '0',
  `icone` varchar(10) null,
  `peso_kg` decimal(6, 3) null,
  `ativo` tinyint(1) not null default '1',
  `off_nome` varchar(200) null,
  `off_marca` varchar(150) null,
  `off_imagem_url` varchar(400) null,
  `off_consultado_em` datetime null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`codigo_barras`),
  CONSTRAINT `produto_catalogo_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_material` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- coleta_item  (migration 2026_11_16_000010_create_coleta_item_table.php)
CREATE TABLE IF NOT EXISTS `coleta_item` (
  `id` bigint unsigned not null auto_increment,
  `coleta_id` bigint unsigned not null,
  `categoria_id` bigint unsigned not null,
  `produto_codigo` varchar(14) null,
  `quantidade` decimal(10, 2) not null,
  `peso_kg` decimal(10, 3) not null default '0',
  `coins` int unsigned not null default '0',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `coleta_item_coleta_id_foreign` FOREIGN KEY (`coleta_id`) REFERENCES `coleta` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `coleta_item_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_material` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `coleta_item_produto_codigo_foreign` FOREIGN KEY (`produto_codigo`) REFERENCES `produto_catalogo` (`codigo_barras`) ON DELETE SET NULL,
  CONSTRAINT `chk_coleta_item_quantidade` CHECK (quantidade > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- leitura_codigo  (migration 2026_11_16_000011_create_leitura_codigo_table.php)
CREATE TABLE IF NOT EXISTS `leitura_codigo` (
  `id` bigint unsigned not null auto_increment,
  `pessoa_id` bigint unsigned not null,
  `ecoponto_id` bigint unsigned null,
  `sessao_id` bigint unsigned null,
  `codigo_barras` varchar(14) not null,
  `produto_codigo` varchar(14) null,
  `resultado` enum('creditado', 'duplicado', 'nao_participa', 'invalido') not null,
  `coins_creditados` int unsigned not null default '0',
  `idempotency_key` char(36) null,
  `lido_em` datetime not null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leitura_codigo_idempotency_key_unique` (`idempotency_key`),
  KEY `leitura_codigo_codigo_barras_pessoa_id_lido_em_index` (`codigo_barras`, `pessoa_id`, `lido_em`),
  KEY `leitura_codigo_ecoponto_id_lido_em_index` (`ecoponto_id`, `lido_em`),
  CONSTRAINT `leitura_codigo_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `leitura_codigo_ecoponto_id_foreign` FOREIGN KEY (`ecoponto_id`) REFERENCES `ecoponto` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leitura_codigo_sessao_id_foreign` FOREIGN KEY (`sessao_id`) REFERENCES `sessao_ecoponto` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leitura_codigo_produto_codigo_foreign` FOREIGN KEY (`produto_codigo`) REFERENCES `produto_catalogo` (`codigo_barras`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- movimento_coins  (migration 2026_11_16_000012_create_movimento_coins_table.php)
CREATE TABLE IF NOT EXISTS `movimento_coins` (
  `id` bigint unsigned not null auto_increment,
  `pessoa_id` bigint unsigned not null,
  `tipo` enum('checkin', 'entrega', 'leitura', 'resgate', 'estorno', 'ajuste') not null,
  `valor` int not null,
  `saldo_apos` bigint unsigned not null,
  `origem_tipo` varchar(30) null,
  `origem_id` bigint unsigned null,
  `descricao` varchar(255) not null,
  `subtitulo` varchar(150) null,
  `icone` varchar(10) null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movimento_coins_pessoa_id_criado_em_index` (`pessoa_id`, `criado_em`),
  CONSTRAINT `movimento_coins_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_movimento_valor` CHECK (valor <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- recompensa  (migration 2026_11_16_000013_create_recompensa_table.php)
CREATE TABLE IF NOT EXISTS `recompensa` (
  `id` bigint unsigned not null auto_increment,
  `categoria` enum('food', 'transport', 'health', 'shop', 'leisure') not null,
  `icone` varchar(10) null,
  `nome` varchar(150) not null,
  `descricao` varchar(255) null,
  `custo_coins` int unsigned not null,
  `estoque` int unsigned null,
  `ativa` tinyint(1) not null default '1',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_recompensa_custo` CHECK (custo_coins > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- resgate  (migration 2026_11_16_000014_create_resgate_table.php)
CREATE TABLE IF NOT EXISTS `resgate` (
  `id` bigint unsigned not null auto_increment,
  `pessoa_id` bigint unsigned not null,
  `recompensa_id` bigint unsigned not null,
  `custo_coins` int unsigned not null,
  `codigo_cupom` varchar(20) not null,
  `status` enum('emitido', 'utilizado', 'cancelado') not null default 'emitido',
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resgate_codigo_cupom_unique` (`codigo_cupom`),
  KEY `resgate_pessoa_id_criado_em_index` (`pessoa_id`, `criado_em`),
  CONSTRAINT `resgate_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `resgate_recompensa_id_foreign` FOREIGN KEY (`recompensa_id`) REFERENCES `recompensa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_resgate_custo` CHECK (custo_coins > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- desafio_verificacao  (migration 2026_11_16_000015_create_desafio_verificacao_table.php)
CREATE TABLE IF NOT EXISTS `desafio_verificacao` (
  `id` bigint unsigned not null auto_increment,
  `uuid` char(36) not null,
  `conta_tipo` enum('pessoa', 'empresa') not null default 'pessoa',
  `pessoa_id` bigint unsigned null,
  `finalidade` enum('login', 'recuperar_senha') not null,
  `pergunta` enum('nome_mae', 'nascimento', 'cep') not null,
  `tentativas` tinyint unsigned not null default '0',
  `max_tentativas` tinyint unsigned not null default '3',
  `lembrar` tinyint(1) not null default '0',
  `expira_em` datetime not null,
  `resolvido_em` datetime null,
  `bloqueado_em` datetime null,
  `token_reset_hash` char(64) null,
  `token_reset_expira_em` datetime null,
  `token_reset_usado_em` datetime null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `desafio_verificacao_uuid_unique` (`uuid`),
  UNIQUE KEY `desafio_verificacao_token_reset_hash_unique` (`token_reset_hash`),
  CONSTRAINT `desafio_verificacao_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- log_autenticacao  (migration 2026_11_16_000016_create_log_autenticacao_table.php)
CREATE TABLE IF NOT EXISTS `log_autenticacao` (
  `id` bigint unsigned not null auto_increment,
  `pessoa_id` bigint unsigned null,
  `empresa_id` bigint unsigned null,
  `evento` varchar(30) not null,
  `data_hora` datetime not null,
  `metodo_2fa` varchar(60) null,
  `sucesso` tinyint(1) not null,
  `tentativa_n` tinyint unsigned null,
  `ip` varbinary(16) null,
  `user_agent` varchar(255) null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `log_autenticacao_data_hora_index` (`data_hora`),
  CONSTRAINT `log_autenticacao_pessoa_id_foreign` FOREIGN KEY (`pessoa_id`) REFERENCES `pessoa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `log_autenticacao_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresa` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_log_uma_conta` CHECK ((pessoa_id IS NULL AND empresa_id IS NOT NULL) OR (pessoa_id IS NOT NULL AND empresa_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- token_acesso  (migration 2026_11_16_000017_create_token_acesso_table.php)
CREATE TABLE IF NOT EXISTS `token_acesso` (
  `id` bigint unsigned not null auto_increment,
  `tokenable_type` varchar(255) not null,
  `tokenable_id` bigint unsigned not null,
  `name` text not null,
  `token` varchar(64) not null,
  `abilities` text null,
  `last_used_at` datetime null,
  `expires_at` datetime null,
  `criado_em` datetime not null default CURRENT_TIMESTAMP,
  `atualizado_em` datetime not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_acesso_token_unique` (`token`),
  KEY `token_acesso_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`),
  KEY `token_acesso_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
