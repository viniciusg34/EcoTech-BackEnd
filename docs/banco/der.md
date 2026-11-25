# DER — EcoTech

17 tabelas (snake_case, em português). Gerado a partir das migrations. Abra no GitHub ou em https://mermaid.live.

```mermaid
erDiagram
    configuracao {
        varchar chave PK
        varchar valor
        varchar descricao
        tinyint publico
        datetime criado_em
        datetime atualizado_em
    }
    pessoa {
        bigint id PK
        varchar nome
        varchar email UK
        char cpf UK
        char login UK
        varchar senha_hash
        varchar nome_mae
        date nascimento
        enum sexo
        varchar telefone_celular
        varchar telefone_fixo
        char cep
        varchar logradouro
        varchar numero
        varchar bairro
        varchar cidade
        char uf
        enum perfil
        varchar avatar_url
        bigint saldo_coins
        varchar nivel_visto
        json preferencias
        datetime termos_aceitos_em
        tinyint ativo
        datetime anonimizado_em
        datetime criado_em
        datetime atualizado_em
    }
    empresa {
        bigint id PK
        char cnpj UK
        varchar nome_empresa
        varchar razao_social
        varchar email UK
        varchar senha_hash
        varchar endereco
        enum status
        datetime criado_em
        datetime atualizado_em
    }
    categoria_material {
        bigint id PK
        varchar chave UK
        varchar rotulo
        char cor_hex
        int coins_por_unidade
        enum unidade
        varchar icone
        datetime criado_em
        datetime atualizado_em
    }
    ecoponto {
        bigint id PK
        bigint empresa_id FK
        varchar codigo UK
        varchar nome
        varchar descricao
        varchar endereco
        varchar cidade
        varchar estado
        decimal lat
        decimal lng
        enum status
        char segredo_qr
        datetime criado_em
        datetime atualizado_em
    }
    ecoponto_categoria {
        bigint ecoponto_id PK, FK
        bigint categoria_id PK, FK
        datetime criado_em
        datetime atualizado_em
    }
    sessao_ecoponto {
        bigint id PK
        bigint ecoponto_id FK
        bigint pessoa_id FK
        char token UK
        enum status
        datetime iniciada_em
        datetime expira_em
        datetime encerrada_em
        datetime criado_em
        datetime atualizado_em
    }
    coleta {
        bigint id PK
        bigint pessoa_id FK
        bigint ecoponto_id FK
        bigint sessao_id FK
        enum tipo
        int total_coins
        decimal total_kg
        datetime criado_em
        datetime atualizado_em
    }
    produto_catalogo {
        varchar codigo_barras PK
        varchar nome
        bigint categoria_id FK
        int coins
        varchar icone
        decimal peso_kg
        tinyint ativo
        varchar off_nome
        varchar off_marca
        varchar off_imagem_url
        datetime off_consultado_em
        datetime criado_em
        datetime atualizado_em
    }
    coleta_item {
        bigint id PK
        bigint coleta_id FK
        bigint categoria_id FK
        varchar produto_codigo FK
        decimal quantidade
        decimal peso_kg
        int coins
        datetime criado_em
        datetime atualizado_em
    }
    leitura_codigo {
        bigint id PK
        bigint pessoa_id FK
        bigint ecoponto_id FK
        bigint sessao_id FK
        varchar codigo_barras
        varchar produto_codigo FK
        enum resultado
        int coins_creditados
        char idempotency_key UK
        datetime lido_em
        datetime criado_em
        datetime atualizado_em
    }
    movimento_coins {
        bigint id PK
        bigint pessoa_id FK
        enum tipo
        int valor
        bigint saldo_apos
        varchar origem_tipo
        bigint origem_id
        varchar descricao
        varchar subtitulo
        varchar icone
        datetime criado_em
        datetime atualizado_em
    }
    recompensa {
        bigint id PK
        enum categoria
        varchar icone
        varchar nome
        varchar descricao
        int custo_coins
        int estoque
        tinyint ativa
        datetime criado_em
        datetime atualizado_em
    }
    resgate {
        bigint id PK
        bigint pessoa_id FK
        bigint recompensa_id FK
        int custo_coins
        varchar codigo_cupom UK
        enum status
        datetime criado_em
        datetime atualizado_em
    }
    desafio_verificacao {
        bigint id PK
        char uuid UK
        enum conta_tipo
        bigint pessoa_id FK
        enum finalidade
        enum pergunta
        tinyint tentativas
        tinyint max_tentativas
        tinyint lembrar
        datetime expira_em
        datetime resolvido_em
        datetime bloqueado_em
        char token_reset_hash UK
        datetime token_reset_expira_em
        datetime token_reset_usado_em
        datetime criado_em
        datetime atualizado_em
    }
    log_autenticacao {
        bigint id PK
        bigint pessoa_id FK
        bigint empresa_id FK
        varchar evento
        datetime data_hora
        varchar metodo_2fa
        tinyint sucesso
        tinyint tentativa_n
        varbinary ip
        varchar user_agent
        datetime criado_em
        datetime atualizado_em
    }
    token_acesso {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        text name
        varchar token UK
        text abilities
        datetime last_used_at
        datetime expires_at
        datetime criado_em
        datetime atualizado_em
    }
    empresa ||--o{ ecoponto : "empresa_id"
    ecoponto ||--o{ ecoponto_categoria : "ecoponto_id"
    categoria_material ||--o{ ecoponto_categoria : "categoria_id"
    ecoponto ||--o{ sessao_ecoponto : "ecoponto_id"
    pessoa ||--o{ sessao_ecoponto : "pessoa_id"
    pessoa ||--o{ coleta : "pessoa_id"
    ecoponto ||--o{ coleta : "ecoponto_id"
    sessao_ecoponto |o--o{ coleta : "sessao_id"
    categoria_material |o--o{ produto_catalogo : "categoria_id"
    coleta ||--o{ coleta_item : "coleta_id"
    categoria_material ||--o{ coleta_item : "categoria_id"
    produto_catalogo |o--o{ coleta_item : "produto_codigo"
    pessoa ||--o{ leitura_codigo : "pessoa_id"
    ecoponto |o--o{ leitura_codigo : "ecoponto_id"
    sessao_ecoponto |o--o{ leitura_codigo : "sessao_id"
    produto_catalogo |o--o{ leitura_codigo : "produto_codigo"
    pessoa ||--o{ movimento_coins : "pessoa_id"
    pessoa ||--o{ resgate : "pessoa_id"
    recompensa ||--o{ resgate : "recompensa_id"
    pessoa |o--o{ desafio_verificacao : "pessoa_id"
    pessoa |o--o{ log_autenticacao : "pessoa_id"
    empresa |o--o{ log_autenticacao : "empresa_id"
```

Relações que não são FK no banco: `token_acesso.tokenable_type/tokenable_id` aponta para `pessoa` ou `empresa` (morph do Sanctum); `movimento_coins.origem_tipo/origem_id` aponta para a coleta, leitura ou resgate que gerou o lançamento.
