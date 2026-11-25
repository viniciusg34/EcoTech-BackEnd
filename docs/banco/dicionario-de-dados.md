# Dicionário de dados — EcoTech

Gerado a partir das migrations (fonte da verdade). Tipos do MySQL 8. Todas as tabelas têm `criado_em` e `atualizado_em`.

## configuracao

Parâmetros das regras de negócio (moedas e kg do check-in, cooldown, duplicidade, duração da sessão).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `chave` | varchar(50) | não |  | PK |
| `valor` | varchar(255) | não |  |  |
| `descricao` | varchar(255) | sim |  |  |
| `publico` | tinyint(1) | não | 0 | 1 = aparece em GET /config |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

## pessoa

Conta de pessoa (perfil comum ou master). O saldo é um cache do livro-razão.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `nome` | varchar(80) | não |  | 15 a 80 caracteres |
| `email` | varchar(255) | não |  | único; também não pode repetir o e-mail de uma empresa (regra da API) |
| `cpf` | char(11) | sim |  | único; só dígitos, dígitos verificadores conferidos; NULL depois de anonimizar |
| `login` | char(6) | sim |  | único; exatamente 6 letras; o master usa MASTER |
| `senha_hash` | varchar(255) | não |  | bcrypt (Hash::make); nunca sai na API |
| `nome_mae` | varchar(150) | sim |  | resposta do 2FA (comparada sem acento e sem maiúsculas) |
| `nascimento` | date | sim |  | resposta do 2FA (dd/mm/aaaa) |
| `sexo` | enum('feminino', 'masculino', 'outro', 'nao_informar') | sim |  |  |
| `telefone_celular` | varchar(11) | sim |  | DDD + 9 dígitos |
| `telefone_fixo` | varchar(10) | sim |  | DDD + 8 dígitos |
| `cep` | char(8) | sim |  | resposta do 2FA; só dígitos |
| `logradouro` | varchar(150) | sim |  |  |
| `numero` | varchar(20) | sim |  |  |
| `bairro` | varchar(100) | sim |  |  |
| `cidade` | varchar(100) | sim |  |  |
| `uf` | char(2) | sim |  |  |
| `perfil` | enum('comum', 'master') | não | comum | comum ou master |
| `avatar_url` | varchar(400) | sim |  |  |
| `saldo_coins` | bigint unsigned | não | 0 | cache do livro-razão; CHECK >= 0 |
| `nivel_visto` | varchar(30) | sim |  | último nível mostrado (controla o confete de nivel.subiu) |
| `preferencias` | json | sim |  | preferências do perfil (JSON) |
| `termos_aceitos_em` | datetime | sim |  | quando aceitou os termos |
| `ativo` | tinyint(1) | não | 1 | 0 depois de anonimizar |
| `anonimizado_em` | datetime | sim |  | preenchido quando a conta é excluída (LGPD) |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: CHECK `chk_pessoa_saldo`: saldo_coins >= 0.

## empresa

Empresa parceira, dona dos Ecopontos.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `cnpj` | char(14) | não |  | único; só dígitos, dígitos verificadores conferidos |
| `nome_empresa` | varchar(150) | não |  |  |
| `razao_social` | varchar(150) | sim |  |  |
| `email` | varchar(255) | não |  | único |
| `senha_hash` | varchar(255) | não |  |  |
| `endereco` | varchar(255) | sim |  |  |
| `status` | enum('ativa', 'suspensa') | não | ativa | suspensa = não entra (tokens revogados) |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

## categoria_material

Materiais recicláveis, com cor, ícone e moedas por unidade (kg ou un).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `chave` | varchar(20) | não |  | único |
| `rotulo` | varchar(50) | não |  |  |
| `cor_hex` | char(7) | não |  | cor usada no mapa e nos gráficos |
| `coins_por_unidade` | int unsigned | não |  | moedas por kg ou por unidade |
| `unidade` | enum('kg', 'un') | não |  |  |
| `icone` | varchar(10) | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

## ecoponto

Ponto de coleta de uma empresa. O kg coletado não é guardado: sai das coletas (view).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `empresa_id` | bigint unsigned | não |  | FK → empresa.id (ON DELETE RESTRICT) |
| `codigo` | varchar(20) | não |  | único; ex.: ECO-01; gerado se não for enviado |
| `nome` | varchar(150) | não |  |  |
| `descricao` | varchar(255) | sim |  |  |
| `endereco` | varchar(255) | sim |  |  |
| `cidade` | varchar(100) | sim |  |  |
| `estado` | varchar(2) | sim |  |  |
| `lat` | decimal(9, 6) | não |  | CHECK entre -90 e 90 |
| `lng` | decimal(9, 6) | não |  | CHECK entre -180 e 180 |
| `status` | enum('ativo', 'manutencao', 'inativo') | não | ativo |  |
| `segredo_qr` | char(64) | não |  | 64 hex; entra na assinatura HMAC do QR; nunca sai na API |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (lat, lng); CHECK `chk_ecoponto_lat`: lat BETWEEN -90 AND 90; CHECK `chk_ecoponto_lng`: lng BETWEEN -180 AND 180.

## ecoponto_categoria

Materiais aceitos em cada Ecoponto (N:N).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `ecoponto_id` | bigint unsigned | não |  | PK; FK → ecoponto.id (ON DELETE CASCADE) |
| `categoria_id` | bigint unsigned | não |  | PK; FK → categoria_material.id (ON DELETE RESTRICT) |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: PK composta (ecoponto_id, categoria_id).

## sessao_ecoponto

Vínculo pessoa-Ecoponto criado quando a pessoa lê o QR da placa; vence em N minutos.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `ecoponto_id` | bigint unsigned | não |  | FK → ecoponto.id (ON DELETE RESTRICT) |
| `pessoa_id` | bigint unsigned | não |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `token` | char(64) | não |  | único; 64 hex aleatórios |
| `status` | enum('ativa', 'encerrada', 'expirada') | não | ativa |  |
| `iniciada_em` | datetime | não |  |  |
| `expira_em` | datetime | não |  | iniciada_em + sessao_minutos |
| `encerrada_em` | datetime | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (ecoponto_id, status); índice (pessoa_id, status).

## coleta

Check-in ou entrega de uma pessoa em um Ecoponto.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `pessoa_id` | bigint unsigned | não |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `ecoponto_id` | bigint unsigned | não |  | FK → ecoponto.id (ON DELETE RESTRICT) |
| `sessao_id` | bigint unsigned | sim |  | FK → sessao_ecoponto.id (ON DELETE SET NULL) |
| `tipo` | enum('checkin', 'entrega') | não |  |  |
| `total_coins` | int unsigned | não | 0 | calculado no servidor |
| `total_kg` | decimal(10, 2) | não | 0 | soma dos itens (ou checkin_kg) |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (pessoa_id, criado_em); índice (ecoponto_id, criado_em).

## produto_catalogo

Produtos que participam da campanha (código de barras), com cache do Open Food Facts.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `codigo_barras` | varchar(14) | não |  | PK |
| `nome` | varchar(150) | não |  |  |
| `categoria_id` | bigint unsigned | sim |  | FK → categoria_material.id (ON DELETE SET NULL) |
| `coins` | int unsigned | não | 0 |  |
| `icone` | varchar(10) | sim |  |  |
| `peso_kg` | decimal(6, 3) | sim |  |  |
| `ativo` | tinyint(1) | não | 1 |  |
| `off_nome` | varchar(200) | sim |  |  |
| `off_marca` | varchar(150) | sim |  |  |
| `off_imagem_url` | varchar(400) | sim |  |  |
| `off_consultado_em` | datetime | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

## coleta_item

Itens de uma coleta (por categoria ou por produto lido).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `coleta_id` | bigint unsigned | não |  | FK → coleta.id (ON DELETE RESTRICT) |
| `categoria_id` | bigint unsigned | não |  | FK → categoria_material.id (ON DELETE RESTRICT) |
| `produto_codigo` | varchar(14) | sim |  | FK → produto_catalogo.codigo_barras (ON DELETE SET NULL) |
| `quantidade` | decimal(10, 2) | não |  | CHECK > 0 |
| `peso_kg` | decimal(10, 3) | não | 0 | kg do item (para os gráficos) |
| `coins` | int unsigned | não | 0 |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: CHECK `chk_coleta_item_quantidade`: quantidade > 0.

## leitura_codigo

Cada leitura de código de barras, com o resultado (creditado, duplicado, não participa, inválido).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `pessoa_id` | bigint unsigned | não |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `ecoponto_id` | bigint unsigned | sim |  | FK → ecoponto.id (ON DELETE SET NULL) |
| `sessao_id` | bigint unsigned | sim |  | FK → sessao_ecoponto.id (ON DELETE SET NULL) |
| `codigo_barras` | varchar(14) | não |  |  |
| `produto_codigo` | varchar(14) | sim |  | FK → produto_catalogo.codigo_barras (ON DELETE SET NULL) |
| `resultado` | enum('creditado', 'duplicado', 'nao_participa', 'invalido') | não |  | creditado, duplicado, nao_participa ou invalido |
| `coins_creditados` | int unsigned | não | 0 |  |
| `idempotency_key` | char(36) | sim |  | único; UUID da Idempotency-Key (única) |
| `lido_em` | datetime | não |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (codigo_barras, pessoa_id, lido_em).

## movimento_coins

Livro-razão das moedas: só INSERT. O saldo da pessoa é a soma dos valores.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `pessoa_id` | bigint unsigned | não |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `tipo` | enum('checkin', 'entrega', 'leitura', 'resgate', 'estorno', 'ajuste') | não |  |  |
| `valor` | int | não |  | negativo no débito; CHECK <> 0 |
| `saldo_apos` | bigint unsigned | não |  | saldo da pessoa depois deste lançamento |
| `origem_tipo` | varchar(30) | sim |  |  |
| `origem_id` | bigint unsigned | sim |  |  |
| `descricao` | varchar(255) | não |  |  |
| `subtitulo` | varchar(150) | sim |  |  |
| `icone` | varchar(10) | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (pessoa_id, criado_em); CHECK `chk_movimento_valor`: valor <> 0.

## recompensa

Recompensas que a pessoa troca por moedas.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `categoria` | enum('food', 'transport', 'health', 'shop', 'leisure') | não |  |  |
| `icone` | varchar(10) | sim |  |  |
| `nome` | varchar(150) | não |  |  |
| `descricao` | varchar(255) | sim |  |  |
| `custo_coins` | int unsigned | não |  | CHECK > 0 |
| `estoque` | int unsigned | sim |  | NULL = ilimitado |
| `ativa` | tinyint(1) | não | 1 |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: CHECK `chk_recompensa_custo`: custo_coins > 0.

## resgate

Troca de moedas por uma recompensa, com o código do cupom.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `pessoa_id` | bigint unsigned | não |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `recompensa_id` | bigint unsigned | não |  | FK → recompensa.id (ON DELETE RESTRICT) |
| `custo_coins` | int unsigned | não |  | custo no momento do resgate; CHECK > 0 |
| `codigo_cupom` | varchar(20) | não |  | único; único, formato CUP-XXXXXX |
| `status` | enum('emitido', 'utilizado', 'cancelado') | não | emitido |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (pessoa_id, criado_em); CHECK `chk_resgate_custo`: custo_coins > 0.

## desafio_verificacao

Pergunta de segurança do 2FA e da recuperação de senha (3 tentativas, 5 minutos).

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `uuid` | char(36) | não |  | único; id público do desafio (desafio_id na API) |
| `conta_tipo` | enum('pessoa', 'empresa') | não | pessoa |  |
| `pessoa_id` | bigint unsigned | sim |  | FK → pessoa.id (ON DELETE SET NULL) |
| `finalidade` | enum('login', 'recuperar_senha') | não |  |  |
| `pergunta` | enum('nome_mae', 'nascimento', 'cep') | não |  |  |
| `tentativas` | tinyint unsigned | não | 0 |  |
| `max_tentativas` | tinyint unsigned | não | 3 | 3 (enunciado) |
| `lembrar` | tinyint(1) | não | 0 |  |
| `expira_em` | datetime | não |  |  |
| `resolvido_em` | datetime | sim |  |  |
| `bloqueado_em` | datetime | sim |  |  |
| `token_reset_hash` | char(64) | sim |  | único; hash do token de troca de senha (10 min) |
| `token_reset_expira_em` | datetime | sim |  |  |
| `token_reset_usado_em` | datetime | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

## log_autenticacao

Log de autenticação (sucessos e falhas), consultado pelo master.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `pessoa_id` | bigint unsigned | sim |  | FK → pessoa.id (ON DELETE RESTRICT) |
| `empresa_id` | bigint unsigned | sim |  | FK → empresa.id (ON DELETE RESTRICT) |
| `evento` | varchar(30) | não |  |  |
| `data_hora` | datetime | não |  |  |
| `metodo_2fa` | varchar(60) | sim |  | texto da pergunta usada |
| `sucesso` | tinyint(1) | não |  |  |
| `tentativa_n` | tinyint unsigned | sim |  |  |
| `ip` | varbinary(16) | sim |  | IPv4/IPv6 em binário (VARBINARY 16) |
| `user_agent` | varchar(255) | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (data_hora); CHECK `chk_log_uma_conta`: (pessoa_id IS NULL AND empresa_id IS NOT NULL) OR (pessoa_id IS NOT NULL AND empresa_id IS NULL).

## token_acesso

Tokens de acesso do Sanctum (pessoa ou empresa); guarda só o hash.

| Coluna | Tipo | Nulo | Padrão | Regra |
|---|---|---|---|---|
| `id` | bigint unsigned | não |  | PK (auto incremento) |
| `tokenable_type` | varchar(255) | não |  |  |
| `tokenable_id` | bigint unsigned | não |  |  |
| `name` | text | não |  |  |
| `token` | varchar(64) | não |  | único; SHA-256 do token (o token em si só aparece uma vez, no login) |
| `abilities` | text | sim |  | papel: pessoa, master ou empresa |
| `last_used_at` | datetime | sim |  |  |
| `expires_at` | datetime | sim |  |  |
| `criado_em` | datetime | não | CURRENT_TIMESTAMP |  |
| `atualizado_em` | datetime | não | CURRENT_TIMESTAMP | atualiza sozinho |

Outras regras: índice (tokenable_type, tokenable_id); índice (expires_at).

## Views

| View | O que mostra |
|---|---|
| `vw_ecoponto_resumo` | kg total, número de coletas e última atividade de cada Ecoponto |
| `vw_empresa_kg_mensal` | kg por mês, últimos 6 meses, por empresa |
| `vw_empresa_categoria` | kg por categoria de material, por empresa |
