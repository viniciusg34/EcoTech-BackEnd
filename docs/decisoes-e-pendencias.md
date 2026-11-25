# Decisões e pendências

O que foi decidido na API, por quê, e o que ainda depende do grupo. Fontes: o enunciado do trabalho, o documento "EcoTech — Backend (banco de dados + API Laravel)" e o "Ajustes para o Backend (vindos do front)".

## Decisões tomadas

| # | Assunto | Decisão | Por quê |
|---|---|---|---|
| 1 | Sessão | Token Bearer do Sanctum, guardado na tabela `token_acesso`, com validade (8 h ou 30 dias) e revogado no logout. Sem `$_SESSION`/cookie. | O front fica em outro domínio (Vercel) e o documento do back pede Sanctum. O token **é** a sessão do usuário no servidor: dá para listar, expirar e revogar. Se o professor exigir `session_start()`, explicar que é o equivalente para API (ver pendência 1). |
| 2 | Senha da pessoa | **Senha forte** (`SENHA_REGRA=forte`, o padrão): 8+ caracteres com maiúscula, minúscula e número, a mesma regra do front final. `SENHA_REGRA=enunciado` volta para exatamente 8 letras. Vale só ao criar ou trocar a senha; o login só confere o hash. | Decisão do grupo (22/11): o front pede senha forte e o documento do back também. Desvio do enunciado (que pede 8 letras), a justificar na apresentação: é mais segura. Empresa sempre usa a regra forte. |
| 3 | Login | Exatamente 6 letras, guardado em minúsculas (digitar `MASTER` ou `master` dá no mesmo). | Enunciado. |
| 4 | 2FA | Pergunta sorteada entre nome da mãe, nascimento e CEP; 3 tentativas em 5 minutos; na 3ª errada o desafio é bloqueado com a mensagem do enunciado ("3 tentativas sem sucesso! Favor realizar Login novamente."). A pergunta não muda a cada erro. | Enunciado + ajustes do front. |
| 5 | Login com Google | Confere o token do Google no servidor e **também** passa pelo 2FA. | O enunciado exige 2FA em todo login de pessoa. |
| 6 | Cadastro | `POST /pessoas` não devolve token: a pessoa entra pelo login (com 2FA). | Mesmo motivo do item 5. |
| 7 | Telefones | Com DDD: celular 11 dígitos, fixo 10 dígitos (fixo opcional). | O front já manda com DDD. |
| 8 | Fluxo do Ecoponto | A pessoa só lê o QR da placa (`/ecopontos/conectar`) e faz check-in; quem lê os produtos é o Ecoponto, logado como a empresa (`/ecopontos/{id}/leituras`). Criadas `GET` e `DELETE /ecopontos/{id}/sessao-ativa` e `GET`/`DELETE /me/sessao`. | Decisão de produto do front: impede ganhar moedas escaneando a tela de outro celular. |
| 9 | `POST /scans` | Mantido só para o master (testes e apresentação), com Idempotency-Key. | Recomendação dos ajustes do front. |
| 10 | Check-in | Moedas, kg e espera vêm da tabela `configuracao` (50 moedas, 0,5 kg, 6 h por pessoa e Ecoponto). Em espera: `429` com "Próximo check-in em X h". | Documento do back pede configurável e com cooldown. |
| 11 | Saldo insuficiente | `409 saldo_insuficiente` (também `sem_estoque`). | É conflito com o estado atual, não erro de digitação. |
| 12 | Livro-razão | Todo crédito e débito passa pelo `CoinService` (transação + trava da linha da pessoa). `movimento_coins` só aceita INSERT; o saldo da pessoa é um cache da soma. | Saldo nunca negativo e auditoria completa. |
| 13 | Nível e impacto | Calculados na API (`GET /me`). `nivel.subiu` usa a coluna `nivel_visto`: vem `true` só na primeira leitura depois de subir. | Ajustes do front, item 2.2. |
| 14 | Preferências | `GET`/`PUT /me/preferencias` (coluna JSON). | Pergunta 6 dos ajustes. |
| 15 | Master | Não troca a senha por `/me/senha` (o enunciado diz troca de senha só para usuário comum); troca pelo `.env` + `php artisan ecotech:criar-master`. Não pode ser excluído. | Enunciado. |
| 16 | LGPD | Listas com CPF mascarado (`***.456.789-**`), sem nome da mãe, nascimento ou CEP. Excluir conta = anonimizar (o histórico fica). | Documento do back. |
| 17 | Cupom | Formato `CUP-XXXXXX`, sem letras que confundem (O/0, I/1). | Aparece no toast. |
| 18 | Estatísticas | `GET /estatisticas` também traz `empresas` e `kg`. | A página "Sobre nós" precisa desses números. |
| 19 | Produtos do seed | Os códigos de barras do documento tinham o dígito verificador errado; foram corrigidos (tabela nos ajustes do front, item 3). | Senão a própria validação EAN-13 recusaria. |
| 20 | Datas | ISO 8601 em UTC (com `Z`) e texto pronto em horário de Brasília (`data_formatada`). | O front compara `expira_em` com o relógio do aparelho. |
| 21 | Tabelas extras | `cache`, `cache_locks` e `sessions` (do próprio Laravel) só são usadas se o `.env` pedir; o modelo do EcoTech continua com 17 tabelas. Colunas a mais que o documento: `pessoa.nivel_visto`, `preferencias`, `anonimizado_em`; `produto_catalogo.icone`, `peso_kg`; `coleta_item.peso_kg`, `produto_codigo`; `movimento_coins.subtitulo`, `icone`; `desafio_verificacao.uuid`, `lembrar`, `bloqueado_em`, `token_reset_*`; `log_autenticacao.evento`; `categoria_material.icone`. | Necessárias para os ajustes do front e para o 2FA/recuperação. |
| 22 | Google no servidor | O `credential` é conferido no endpoint oficial `tokeninfo` do Google (público-alvo = `GOOGLE_CLIENT_ID`, emissor, validade e e-mail verificado), sem a biblioteca `google/apiclient`. | Mesma verificação com bem menos dependências; fácil de testar (os testes simulam o Google). |
| 23 | Idioma | Mensagens sempre em português (`config/app.php` fixa `pt_BR`, independente do `APP_LOCALE` do `.env`). | O `.env` padrão do Laravel vem com `APP_LOCALE=en`, o que deixava as mensagens de validação em inglês. |
| 24 | Painel da empresa (ajustes 7.1) | `GET /empresas/me/dashboard` traz `totais`, `serie` (rótulos curtos, `valores_formatados`, `total_formatado`), `categorias` com `percentual` **inteiro** somando 100 (método do maior resto) e `ecopontos` com `endereco`, `categorias`, `kg_mes_formatado` e `ultima_atividade` em texto ("Hoje, 08h30"). A data exata foi para `ultima_atividade_iso`. `resumo` e `serie_mensal` continuam. | Formato pedido pelo front; os campos antigos ficaram para não quebrar quem já usava. |
| 25 | Histórico de leituras (ajustes 7.5c) | `GET /empresas/me/leituras?periodo=mes|3m|6m|tudo&ecoponto_id=`: paginado (até 100), do mais recente ao mais antigo, inclusive recusadas. O kg de cada leitura é o `peso_kg` do produto (só nas creditadas). Sem nenhum dado pessoal. Novo índice `leitura_codigo (ecoponto_id, lido_em)`. | O front gera o CSV. Não foi preciso criar `leitura_codigo.coleta_id`: o peso vem do produto. |
| 26 | Lista de usuários e log (ajustes 7.5b) | `/admin/pessoas` mostra só usuários **comuns**, em ordem alfabética, com busca só pelo **nome**. `/admin/logs-autenticacao` mostra só entradas de usuários **comuns** (sem master e sem empresas) e traz também `data_formatada`. Login com e-mail ou login inexistente não entra no log (não há pessoa para registrar). | Enunciado: "lista de todos os usuários comuns que contém a substring como parte de seu nome" e log "com as entradas do usuário comum". |
| 27 | Master por script SQL (ajustes 7.5b) | `03_seed.sql` já traz o hash da senha inicial do master (`Master123`, que segue a regra forte) e só cria o master se ele não existir (rodar de novo não volta a senha). | O enunciado pede o master criado por script SQL. **Trocar a senha antes de publicar** (o arquivo fica no GitHub). |
| 28 | Perfil da empresa no login | `usuario.tipo = "empresa"` e `usuario.perfil = "empresa"` (o front sugeriu `"comum"`). | Decisão do grupo: deixa claro quem é quem; o front pode olhar `tipo` ou `perfil`. |
| 29 | `GET /saude` (ajustes 8.1) | Público, fora do limite de requisições, testa o banco com `select 1`: `200 { status: "ok", banco: "ok", modo: "real", versao }` ou `503` com o banco fora do ar. | O front usa para escolher o ambiente (local ou servidor) e mostrar o selo de conexão. |

## Respostas às perguntas em aberto do front

| Pergunta (ajustes, seção 5) | Resposta |
|---|---|
| 1. `POST /checkins`: corpo e código do cooldown | Corpo `{ "sessao_id": 55 }`. Resposta `{ coinsAwarded, balance, balance_formatado, message }`. Cooldown: `429 checkin_cooldown` com `message` pronta e `proximo_checkin_em`. |
| 2. Tela do Ecoponto | `GET /ecopontos/{id}/sessao-ativa` como proposto (nome abreviado, sem CPF/e-mail) e `DELETE` para encerrar. |
| 3. Telefone com ou sem DDD | Com DDD (11 e 10 dígitos). |
| 4. Desafio falso na recuperação | Confirmado: e-mail desconhecido recebe um desafio falso, sempre com a mesma pergunta, e a verificação falha como se fosse real. |
| 5. `nivel.subiu` | Coluna `pessoa.nivel_visto`. |
| 6. Preferências | `PUT /me/preferencias` (e `GET`). |
| 7. `POST /scans` | Mantido só para master. |
| Etapa 4 — 1. Caminho do log | `/admin/logs-autenticacao` (com hífen). |
| Etapa 4 — 2. `qr.svg` exige token? | Sim: só a empresa dona do ponto. Buscar com o Bearer e mostrar como data URL. |
| Etapa 4 — 3. Login de empresa | `usuario.nome` = nome da empresa, `tipo: "empresa"`, `perfil: "empresa"`. |
| Etapa 4 — 4. E-mail inexistente no log? | Não entra (não há pessoa). |
| Etapa 4 — 5. `por_pagina=100` em `/admin/*` | Aceito em todas as listas (máximo 100). |

## Pendências: dependem do grupo

| # | O que decidir ou fazer | Sugestão |
|---|---|---|
| 1 | Confirmar com o professor se o token (Sanctum) atende ao requisito "sessão" do enunciado. | Mostrar a tabela `token_acesso`, o logout revogando o token e o 401 depois de vencer. |
| 2 | Onde publicar a API com HTTPS (hospedagem com SSH ou VPS) e o domínio. | Depois: `APP_URL`, `CORS_ORIGINS` e o `BASE_URL` do front. |
| 3 | Banco de produção. | No Aiven, criar um banco **separado** (`ecotech`) para não mexer no `defaultdb` do colega; ou combinar com quem cuida do banco. Trocar a senha do Aiven, que já foi compartilhada em conversa. |
| 4 | `GOOGLE_CLIENT_ID` | O mesmo Client ID que o front usa no botão do Google. |
| 5 | `QR_SECRET`, `MASTER_PASSWORD` e as respostas de segurança do master (a senha inicial do script SQL é pública). | Gerar e guardar só no `.env` de produção. |
| 6 | Importar os dados antigos (MockAPI ou `defaultdb`)? | Rodar primeiro com `--simular` e conferir os avisos (ver `database/sql/04_migracao_mockapi.md`). |
| 7 | Quem migra o front e quando. | Entregar `docs/openapi.yaml` e `docs/guia-migracao-front.md` para o time do front. |
| 8 | Códigos de barras para a apresentação. | Imprimir etiquetas com os 6 códigos da campanha ou cadastrar produtos reais em `/admin/produtos`. |
| 9 | Senha inicial do master no `03_seed.sql` (`Master123`). | Trocar antes de publicar a API: `MASTER_PASSWORD` no `.env` + `php artisan ecotech:criar-master`. |
