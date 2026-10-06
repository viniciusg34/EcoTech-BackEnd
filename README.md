# EcoTech - Back-End (PHP + MySQL)

Back-end do projeto da disciplina **Projeto de Desenvolvimento em Back-End** (UNISUAM - ADS).

## Estrutura

```
api/                 -> endpoints que o front-end chama
  usuario.php        -> cadastro, consulta, exclusão, moedas
  login.php          -> login etapa 1 (login + senha)
  2fa.php            -> login etapa 2 (pergunta de segurança, 3 tentativas)
  sessao.php         -> quem está logado (login no canto da tela + menu por perfil)
  logout.php
  alterar_senha.php  -> só usuário comum
  logs.php           -> tela de LOG, só master
  usuarios_pdf.php   -> desafio plus: lista de usuários em PDF, só master
  empresa.php, ecopontos.php -> recursos do EcoTech (empresas parceiras e pontos de coleta)
includes/            -> código compartilhado (sessão, CORS, validações, PDF)
db/connection.php    -> conexão com o MySQL (Aiven)
db/config.example.php-> modelo da configuração (copiar para db/config.php)
sql/estrutura_esperada.sql -> tabelas que o back-end espera + usuário master
ferramentas/ver_banco.php  -> testa a conexão e lista as tabelas do banco
```

## Como configurar

1. Copie `db/config.example.php` para `db/config.php` e preencha com os dados do Aiven.
2. Baixe o **CA certificate** no painel do Aiven e salve como `db/ca.pem`.
3. Abra `http://localhost/<pasta>/ferramentas/ver_banco.php` para testar a conexão.

`db/config.php` e `db/ca.pem` estão no `.gitignore` (têm a senha do banco).

## Regra para o front-end

A sessão usa cookie. **Todo `fetch` para a API precisa de `credentials: "include"`**:

```js
const API = "http://localhost/EcoTech-BackEnd/api"; // trocar pelo endereço real

const resp = await fetch(`${API}/login.php`, {
  method: "POST",
  credentials: "include",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ login, senha }),
});
const json = await resp.json(); // { sucesso: true, dados: ... } ou { sucesso: false, erro: "..." }
```

O endereço do site precisa estar em `origens_permitidas` no `db/config.php`.

## Endpoints

Todas as respostas são `{ "sucesso": true, "dados": ... }` ou `{ "sucesso": false, "erro": "mensagem" }`.

| Método | Endpoint | Quem | Corpo / parâmetros |
|---|---|---|---|
| POST | `usuario.php` | qualquer um | `nome, data_nascimento, sexo, nome_materno, cpf, email, telefone_celular, telefone_fixo, cep, endereco, login, senha, confirmar_senha` |
| POST | `login.php` | qualquer um | `login` (ou e-mail), `senha` → devolve `pergunta` do 2FA |
| GET | `2fa.php` | após login | devolve a pergunta sorteada |
| POST | `2fa.php` | após login | `resposta` |
| GET | `sessao.php` | qualquer um | devolve `logado` e `usuario { id, nome, login, perfil }` |
| POST | `logout.php` | logado | — |
| GET | `usuario.php?busca=ana` | master | lista usuários comuns com "ana" no nome |
| DELETE | `usuario.php?id=5` | master | exclui usuário comum |
| POST | `alterar_senha.php` | comum | `senha_atual, nova_senha, confirmar_senha` |
| GET | `logs.php?filtro=nome\|cpf\|todos&valor=...` | master | logs do mais recente ao mais antigo |
| GET | `usuarios_pdf.php?busca=...` | master | baixa o PDF |

### Erros de cadastro

Quando algum campo está errado, a API devolve **422** com os erros por campo, para mostrar embaixo de cada input:

```json
{ "sucesso": false, "erro": "Verifique os campos do cadastro",
  "campos": { "cpf": "CPF inválido", "login": "O login deve ter exatamente 6 letras" } }
```

### 2FA

- Resposta errada: **401** com `tentativas_restantes`.
- Na 3ª errada: **401** com `erro: "3 tentativas sem sucesso! Favor realizar Login novamente."` e `voltar_login: true` → o front volta para a tela de login.
- Resposta da data aceita `25/12/2000` ou `2000-12-25`. CEP aceita com ou sem traço. Nome da mãe ignora maiúsculas e acentos.

### Regras de validação (enunciado)

- Nome: 15 a 80 caracteres, só letras
- CPF: dígito verificador
- Telefones: `(+55)XX-XXXXXXXX` (aceita 8 ou 9 números no final)
- Login: exatamente 6 letras
- Senha: exatamente 8 letras, guardada com `password_hash()`
- Senha e confirmação iguais

## Usuário master

Criado pelo script `sql/estrutura_esperada.sql`: login **master**, senha **Ecotechm**.
