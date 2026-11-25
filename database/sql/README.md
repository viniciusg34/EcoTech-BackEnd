# SQL de referência (MySQL 8)

A fonte da verdade do banco são as **migrations** do Laravel (`database/migrations`): com a API, o banco é criado por `php artisan migrate --seed`. Estes arquivos mostram o mesmo banco em SQL puro, para estudar, conferir no phpMyAdmin ou montar o banco sem o Laravel. Os arquivos 01 e 03 são gerados a partir das migrations e dos seeders.

| Arquivo | O que faz |
|---|---|
| `01_schema.sql` | As 17 tabelas, com chaves, índices, unicidades e regras CHECK. |
| `02_views_procedures.sql` | As 3 views dos painéis e as procedures `sp_creditar_coins` e `sp_resgatar`. |
| `03_seed.sql` | Dados iniciais: configurações, 6 categorias, 11 recompensas, 6 produtos, 3 empresas fictícias, 22 Ecopontos e o master, como pede o enunciado (senha inicial `Master123`, já em hash; troque antes de publicar). |
| `04_migracao_mockapi.md` | Como trazer os dados antigos (MockAPI ou o banco antigo). |
| `05_testes.sql` | Testes do banco: saldo nunca negativo, resgate concorrente, unicidades, CHECKs, anonimização e views. |

Todos podem rodar mais de uma vez num MySQL 8 limpo, nesta ordem:

```bash
mysql -u root -p ecotech < 01_schema.sql
mysql -u root -p ecotech < 02_views_procedures.sql
mysql -u root -p ecotech < 03_seed.sql
mysql -u root -p ecotech < 05_testes.sql      # num banco de teste
```

No phpMyAdmin: selecione o banco, aba **Importar**, um arquivo de cada vez.

## Por que assim (decisões não óbvias)

1. **Livro-razão (`movimento_coins`)**: moeda nunca é só um número que se sobrescreve. Cada crédito ou débito vira uma linha imutável com o saldo depois dela; `pessoa.saldo_coins` é só um atalho, sempre igual à soma. Dá para auditar e refazer qualquer saldo.
2. **Trava da pessoa (`SELECT ... FOR UPDATE`)**: dois resgates ao mesmo tempo esperam um pelo outro, então o segundo já vê o saldo novo. Junto com o `CHECK (saldo_coins >= 0)`, o saldo nunca fica negativo.
3. **Sessão de Ecoponto (`sessao_ecoponto`)**: ler o QR da placa prova que a pessoa está no ponto e abre uma janela curta (10 min) em que o Ecoponto credita para ela. Sem isso, qualquer um ganharia moedas escaneando de casa.
4. **Cache do Open Food Facts em `produto_catalogo`**: a API externa é lenta e pode cair; o nome e a marca ficam salvos, a leitura continua funcionando sem ela e não repetimos a consulta.
5. **Anonimizar em vez de apagar (LGPD)**: os dados pessoais somem, mas coletas e moedas continuam (as FKs com RESTRICT impedem apagar o histórico sem querer).
