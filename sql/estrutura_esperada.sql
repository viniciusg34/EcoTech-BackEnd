-- =================================================================
-- ESTRUTURA QUE O BACK-END ESPERA (tabelas de usuário e LOG)
-- -----------------------------------------------------------------
-- O banco é responsabilidade do colega do grupo. Este arquivo NÃO é
-- para rodar no Aiven sem conversar com ele: serve para comparar com
-- o banco real e ver se falta alguma coluna.
-- Se os nomes no Aiven forem diferentes, ajustamos o PHP.
-- =================================================================

CREATE TABLE IF NOT EXISTS usuario (
    user_id           INT AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(80)  NOT NULL,
    data_nascimento   DATE         NOT NULL,
    sexo              CHAR(1)      NOT NULL,              -- M, F ou O
    nome_materno      VARCHAR(80)  NOT NULL,
    cpf               CHAR(11)     NOT NULL UNIQUE,       -- só números
    email             VARCHAR(120) NOT NULL UNIQUE,
    telefone_celular  VARCHAR(20)  NOT NULL,              -- (+55)21-999999999
    telefone_fixo     VARCHAR(20)  NOT NULL,              -- (+55)21-22222222
    cep               CHAR(8)      NOT NULL,              -- só números
    endereco          VARCHAR(255) NOT NULL,
    login             CHAR(6)      NOT NULL UNIQUE,
    senha             VARCHAR(255) NOT NULL,              -- hash do password_hash() (~60 caracteres)
    perfil            ENUM('master', 'comum') NOT NULL DEFAULT 'comum',
    coins             DECIMAL(10,2) NOT NULL DEFAULT 0,   -- recurso do EcoTech (moedas de reciclagem)
    avatar            VARCHAR(255) NULL,
    criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- LOG de autenticação: uma linha por login completo (senha + 2FA) de usuário comum.
-- Nome e CPF são copiados para o log continuar existindo mesmo se o usuário for excluído.
CREATE TABLE IF NOT EXISTS log_autenticacao (
    log_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NULL,
    nome          VARCHAR(80)  NOT NULL,
    cpf           CHAR(11)     NOT NULL,
    pergunta_2fa  VARCHAR(60)  NOT NULL,
    data_hora     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuario(user_id) ON DELETE SET NULL
);

-- Usuário MASTER (o enunciado pede que ele seja criado por script SQL).
-- Login: master   |   Senha: Ecotechm   (troque depois, se quiser)
-- A senha abaixo já está criptografada com password_hash().
-- Para gerar outra:  php -r "echo password_hash('NovaSenha', PASSWORD_DEFAULT);"
INSERT INTO usuario
    (nome, data_nascimento, sexo, nome_materno, cpf, email, telefone_celular,
     telefone_fixo, cep, endereco, login, senha, perfil)
VALUES
    ('Administrador Master EcoTech', '2000-01-01', 'O', 'Maria da Silva', '52998224725',
     'master@ecotech.com', '(+55)21-999999999', '(+55)21-22222222', '20000000',
     'Rua Exemplo, 100 - Centro - Rio de Janeiro/RJ', 'master',
     '$2y$10$3xtUxOQAMk/v5Gf8z35rKO4iBV2GkQJBLwsBTO0ZGujoE1PIwgyoq', 'master');
