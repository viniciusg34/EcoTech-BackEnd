# Guia de migração do front para a API EcoTech

Para quem mexe no front (repositório `EcoTech-FronEnd`). Hoje cada página chama direto a MockAPI, o ViaCEP, o Open Food Facts e guarda senha e saldo no navegador. Com a API, **todas as chamadas vão para um endereço só** e o servidor faz as contas, as validações e a formatação.

Detalhes de cada rota (campos, erros e exemplos reais): [`openapi.yaml`](openapi.yaml). Para testar sem o front: [`EcoTech.postman_collection.json`](EcoTech.postman_collection.json).

## 1. Endereço base (BASE_URL)

Ponha o endereço num lugar só (por exemplo em `js/api.js`):

```js
const BASE_URL = "http://127.0.0.1:8000/api/v1";        // API rodando no seu PC (php artisan serve)
// const BASE_URL = "https://api.seudominio.com.br/api/v1"; // API publicada (precisa de HTTPS)
```

O `/api/v1` faz parte do endereço. A Vercel e o Live Server (`http://127.0.0.1:5500`) já estão liberados no CORS.

## 2. Como chamar a API

Uma função para todas as chamadas:

```js
async function api(caminho, { metodo = "GET", corpo, idempotente = false } = {}) {
  const headers = { Accept: "application/json" };
  const token = sessionStorage.getItem("eco_token") || localStorage.getItem("eco_token");
  if (token) headers.Authorization = "Bearer " + token;
  if (corpo) headers["Content-Type"] = "application/json";
  if (idempotente) headers["Idempotency-Key"] = crypto.randomUUID();

  try {
    const resposta = await fetch(BASE_URL + caminho, {
      method: metodo,
      headers,
      body: corpo ? JSON.stringify(corpo) : undefined,
    });
    const dados = resposta.status === 204 ? null : await resposta.json().catch(() => null);
    if (resposta.status === 401 && token) {           // token venceu ou foi revogado
      sessionStorage.removeItem("eco_token");
      localStorage.removeItem("eco_token");
    }
    return { ok: resposta.ok, status: resposta.status, dados };
  } catch (_) {
    return { ok: false, status: 0, dados: { erro: "sem_conexao", message: "Não foi possível falar com o servidor." } };
  }
}
```

Regras que valem para todas as rotas:

| Assunto | Como é |
|---|---|
| Login | Sem cookies. O token vem no login e vai em todo pedido no cabeçalho `Authorization: Bearer {token}`. Com "lembrar-me" o token vale 30 dias (guarde no `localStorage`); sem, 8 horas (guarde no `sessionStorage`). |
| Quem está logado | `usuario.tipo` no login: `pessoa` ou `empresa`. Master é `tipo: "pessoa"` com `perfil: "master"`. A empresa também tem o campo `cnpj`. |
| Erros | Sempre `{ "erro": "codigo", "message": "texto pronto", "campos": { "email": ["..."] } }`. Mostre só a `message` no toast; use `campos` para marcar o campo errado. |
| Listas | `{ "data": [...], "meta": { "total", "pagina", "por_pagina", "ultima_pagina" } }`. Peça `?pagina=1&por_pagina=100` (máximo 100) e junte as páginas até `meta.total`. |
| Idempotency-Key | Obrigatória em `POST /resgates` e `POST /scans`; recomendada em `POST /ecopontos/{id}/leituras` e `POST /coletas`. Um UUID novo por ação (`crypto.randomUUID()`). Se a rede cair e o front repetir o pedido com a mesma chave, nada é gravado duas vezes. |
| Textos e números | Já chegam prontos: `data_formatada` ("10/11/2026 às 14:32"), `coins_formatado` ("1.250"), `*_rotulo`, `cor_hex`, `icone`, `message`. O front não precisa calcular nem formatar. |
| Limites | Em 429 a `message` já diz o que fazer (e o cabeçalho `Retry-After` diz em quantos segundos). No `GET /me/coins` a cada 5 s, ignore o 429 em silêncio. |

Pode apagar do navegador as chaves antigas: `ecotech_session`, `ecotech_pending_2fa`, `ecotech_user_id`, `ecotech_master_users`, `ecotech_local_users`, `usuariosEcoTech`, `empresasEcoTech`, `sessaoEcoTech`, `ecotech_points`, `demoBal` e os logs de autenticação guardados no `localStorage`.

## 3. Arquivo por arquivo (branch `main`)

| Arquivo | Chamada atual | Chamada nova |
|---|---|---|
| `index.html` | MockAPI `GET /empresa` (soma `kgColetado`), `GET /pessoa` (conta membros) e `GET /ecopontos` (conta pontos) | `GET /estatisticas` → `{ toneladas, membros, ecopontos, empresas, kg }` (um pedido só) |
| `sobre_nos.html`, `Sobre_nos.html` | MockAPI `GET /empresa` + `GET /empresa/:id/ecopontos` para cada empresa | `GET /estatisticas` → `empresas`, `kg`, `ecopontos` |
| `afilie_se.html` | MockAPI `GET /empresa` + `GET /empresa/:id/ecopontos` | `GET /estatisticas` → `ecopontos` |
| `js/map.js` + `js/data-map-points.js` | lista fixa no JS | `GET /ecopontos?pagina=1&por_pagina=100` (e `&categoria=metal` para filtrar); juntar as páginas até `meta.total` |
| `cadastro.html` | MockAPI `GET /pessoa` (procura e-mail repetido no navegador) + `POST /pessoa` (senha em texto puro) | `POST /pessoas` (campos em snake_case, só dígitos em CPF, CEP e telefones). `409` = e-mail, CPF ou login repetido; `422` = `campos` com o que corrigir. Depois vai para o login |
| `cadastro_empresarial.html` | MockAPI `GET /empresa` + `POST /empresa` | `POST /empresas` `{ cnpj, nome_empresa, razao_social, email, senha, endereco }` |
| `js/eco-helpers.js` | ViaCEP direto (`viacep.com.br/ws/{cep}/json/`) | `GET /cep/{cep}` → `{ logradouro, bairro, cidade, uf }` ou `404` |
| `login.html` | MockAPI `GET /pessoa?email=` e `GET /empresa` (compara a senha no navegador) | `POST /auth/login` `{ email` **ou** `login, senha, lembrar }`. Pessoa recebe `{ status: "2fa", desafio_id, pergunta, pergunta_tipo }` → vai para o 2FA. Empresa recebe `{ token, usuario }` direto |
| `login.html` (botão do Google) | Google Identity Services só no navegador | `POST /auth/google` `{ credential }` (o mesmo `credential` do callback do Google). Conta existente → `status: "2fa"` (segue para o 2FA); sem conta → `status: "cadastro_pendente"` com `nome` e `email` para preencher o cadastro |
| `2fa.html` | pergunta e resposta conferidas no navegador | `POST /auth/2fa` `{ desafio_id, resposta, lembrar }` → `{ token, usuario }`. Erro: `422` com `tentativas_restantes`; na 3ª errada, `423` com "3 tentativas sem sucesso! Favor realizar Login novamente." (volte para o login). A pergunta não muda a cada erro. Use `pergunta_tipo` (`nome_mae`, `nascimento`, `cep`) para a máscara do campo |
| `esqueci_senha.html` | MockAPI `GET /pessoa?email=` + `PUT /pessoa/:id` com a senha nova | 3 passos: `POST /auth/esqueci-senha` `{ email }` → `{ desafio_id, pergunta, pergunta_tipo }` (sempre responde assim, exista ou não o e-mail); `POST /auth/esqueci-senha/verificar` `{ desafio_id, resposta }` → `{ token }`; `POST /auth/redefinir-senha` `{ token, nova_senha }` |
| `alterar_senha.html` | MockAPI `GET` + `PUT /pessoa/:id` | `PUT /me/senha` `{ senha_atual, nova_senha }` (só usuário comum; empresa usa `PUT /empresas/me/senha`) |
| `perfil.html` | MockAPI `GET /pessoa` (baixa **todas** as pessoas), `PUT` e `DELETE /pessoa/:id`; nível e impacto calculados no JS | `GET /me` (já traz `nivel` com `meta`, `progresso` e `subiu`, e `impacto`); `PUT /me` `{ nome, email, avatar_url }`; `DELETE /me` (204, anonimiza); histórico: `GET /me/movimentos?por_pagina=20`; cupons: `GET /me/resgates`; notificações: `GET`/`PUT /me/preferencias` |
| `coleta.html`, `js/ecoponto-modal.js` | MockAPI `GET /empresa` + `/empresa/:id/ecopontos`; `PUT /pessoa/:id` somando `coins` e `reciclado[]` no navegador | Ler o QR da placa → `POST /ecopontos/conectar` `{ qr }` → `{ sessao_id, ecoponto, expira_em }`; depois `POST /checkins` `{ sessao_id }` → `{ coinsAwarded, balance, message }` (em espera: `429` com a `message` pronta). Enquanto a sessão vale: `GET /me/coins` e `GET /me/movimentos` a cada 5 s. Sair: `DELETE /me/sessao`. Ao abrir a página: `GET /me/sessao` (404 = não está conectado) |
| `leitor.html`, `leitor-codigo-barras-coins.html` | Open Food Facts direto + `POST /scans` + `GET /me/coins` | Vira a **tela do Ecoponto**, logada como a **empresa**: `GET /ecopontos/{id}/sessao-ativa` (quem está conectado) e, a cada código lido, `POST /ecopontos/{id}/leituras` `{ sessao_id, code }` com `Idempotency-Key`. A API consulta o Open Food Facts (não chame mais direto). Respostas: `200` moedas para a pessoa; `404` fora da campanha; `409` já lido em 24 h; `422` código inválido. `POST /scans` ficou só para o master (testes) |
| `empresa.html`, `js/empresa-data.js` | MockAPI `GET /empresa/:id`; última atividade e gráfico inventados no JS | `GET /empresas/me`; `GET /empresas/me/dashboard` (`totais`, `serie` com `labels`, `valores_formatados` e `total_formatado`, `categorias` com percentual inteiro somando 100, `ecopontos` com `kg_mes_formatado` e `ultima_atividade` em texto); histórico para o CSV: `GET /empresas/me/leituras?periodo=mes&ecoponto_id=3&por_pagina=100` (sem dados pessoais); `GET /empresas/me/ecopontos`; criar/editar ponto: `POST /ecopontos`, `PATCH /ecopontos/{id}`; placa: `GET /ecopontos/{id}/qr.svg` (imagem) ou `/qr` (texto do QR); entrega pesada: `POST /coletas` `{ sessao_id, itens: [{ categoria, quantidade }] }` |
| `js/rewards.js` + `js/data-rewards.js` | saldo em `localStorage` (`ecotech_points`) e lista fixa | `GET /recompensas?cat=food` (`todos` = todas); `POST /resgates` `{ recompensa_id }` com `Idempotency-Key` → `{ message, codigo_cupom }` (mostre o cupom no toast); `409` = saldo insuficiente ou sem estoque; saldo em `GET /me/coins` |
| `js/data-scan-products.js` | lista fixa de produtos | o catálogo fica no servidor (o master gerencia em `GET/POST/PATCH/DELETE /admin/produtos`) |
| `consulta_usuarios.html` | MockAPI `GET /pessoa` + `DELETE /pessoa/:id` | `GET /admin/pessoas?busca=texto&pagina=1` (só usuários comuns, busca pelo nome, CPF já mascarado); `DELETE /admin/pessoas/{id}` (anonimiza); PDF: `GET /admin/pessoas/pdf?busca=texto` (baixe com `fetch` + `blob()`, porque precisa do token) |
| `logs.html` | logs guardados no `localStorage` | `GET /admin/logs-autenticacao?tipo=nome&termo=ana` (`tipo`: `todos`, `nome` ou `cpf`), do mais recente para o mais antigo, só entradas de usuários comuns; data pronta em `data_formatada` |
| `js/eco-session.js`, `js/auth.js`, `js/perfis.js` | sessão e perfil no `localStorage` | guardar só o token; quem é a pessoa vem de `GET /me` (empresa: `GET /empresas/me`). Sair: `POST /auth/logout` e apagar o token |

## 4. Se o front estiver na branch `kaio` (`js/api.js` com sessão por cookie)

A branch `kaio` já tem um `js/api.js` para outra versão da API (sessão por cookie, respostas `{ sucesso, dados }`). Para usar esta API:

- `const API = "http://127.0.0.1:8000/api"` → `BASE_URL` com `/api/v1`.
- Tirar `credentials: "include"` e mandar `Authorization: Bearer {token}`.
- Respostas: o objeto vem direto (sem `sucesso`/`dados`); erro é `{ erro, message }` com o status HTTP (use `resposta.ok`).

| Chamada da branch `kaio` | Chamada nova |
|---|---|
| `GET /sessao` | `GET /me` (pessoa e master) ou `GET /empresas/me` (empresa); `401` = não logado |
| `POST /login` `{ login, senha }` | `POST /auth/login` e, para pessoa, `POST /auth/2fa` |
| `GET` / `POST /2fa` `{ resposta }` | a pergunta vem na resposta do login; `POST /auth/2fa` `{ desafio_id, resposta }` |
| `POST /logout` | `POST /auth/logout` |
| `POST /usuarios` | `POST /pessoas` |
| `PUT /usuarios/coins` | não existe: o servidor calcula as moedas (check-in, leituras e entregas) |
| `POST /alterar-senha` | `PUT /me/senha` |
| `DELETE /usuarios/{id}` | `DELETE /admin/pessoas/{id}` |
| `GET /logs?...` | `GET /admin/logs-autenticacao?tipo=&termo=` |
| `GET /empresas`, `POST /empresas` | `GET /estatisticas` (números públicos) e `POST /empresas` (cadastro) |
| `GET /empresas/{id}` | `GET /empresas/me` e `GET /empresas/me/dashboard` |
| `GET` / `PUT /ecopontos/{id}` | `GET /ecopontos/{id}` (público) e `PATCH /ecopontos/{id}` (empresa dona) |

## 5. Como testar o fluxo inteiro

1. API ligada (`php artisan serve`) com `php artisan migrate --seed` e `php artisan db:seed --class=DemoSeeder`.
2. Pessoa: login `anasil` / `Reciclar1` → pergunta (Maria José da Silva · 12/05/1990 · 20040-020).
3. Empresa (outra aba ou navegador): `contato@costaverde.ecotech.example` / `Ecotech2026` → painel → Ecoponto ECO-01 → a tela mostra o QR (`GET /ecopontos/1/qr.svg`, com o token da empresa).
4. Pessoa lê o QR (ou cola o texto de `/ecopontos/1/qr`) → check-in → a empresa lê o código `7896004004778` (Garrafa PET) → o saldo e o extrato da pessoa mudam em até 5 s.
5. Pessoa resgata uma recompensa; tente uma mais cara que o saldo (409).

Os 6 códigos de barras da campanha (imprima como etiqueta para a apresentação): `7896004004778` Garrafa PET, `7891234567895` Lata de alumínio, `7896666000019` Caixa de papelão, `7899000001239` Vidro de conserva, `7891910000197` Pilha AA, `7896000000002` Tetra Pak.
