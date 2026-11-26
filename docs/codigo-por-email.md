# Código de 6 dígitos por e-mail

## Onde é usado

| Situação | Antes | Agora |
|---|---|---|
| Cadastro de pessoa | conta ativa na hora | `POST /pessoas` cria a conta e manda o código; a pessoa confirma em `POST /auth/confirmar-email` e depois faz login |
| Login de pessoa | senha → pergunta → token | senha → pergunta (exigida pelo enunciado) → **código** → token |
| Login de empresa | senha → token | senha → **código** → token |
| Login com Google | Google → pergunta → token | Google → pergunta → **código** → token (o Google também marca o e-mail como confirmado) |
| Esqueci a senha | pergunta de segurança | **código** no e-mail |

Quem tentar entrar sem ter confirmado o e-mail recebe um novo código de confirmação em vez da pergunta.
As contas que já existiam no banco ficam como confirmadas (coluna `pessoa.email_confirmado`, padrão `true`).

## Rotas novas

| Rota | Corpo | Resposta |
|---|---|---|
| `POST /auth/codigo` | `{ desafio_id, codigo, lembrar? }` | `{ token, usuario }` |
| `POST /auth/confirmar-email` | `{ desafio_id, codigo }` | `{ message }` |
| `POST /auth/codigo/reenviar` | `{ desafio_id }` | mesmos dados do desafio, com prazos novos |

`/auth/2fa` (pessoa), `/auth/login` (empresa, ou pessoa sem e-mail confirmado), `/pessoas` e `/auth/esqueci-senha` respondem:

```json
{
  "status": "codigo_email",
  "finalidade": "login | confirmar_email | recuperar_senha",
  "desafio_id": "uuid",
  "email": "an***@teste.com",
  "digitos": 6,
  "tentativas_restantes": 5,
  "expira_em": "...",
  "reenviar_em": "...",
  "email_enviado": true,
  "message": "..."
}
```

`/auth/esqueci-senha/verificar` agora recebe `{ desafio_id, codigo }` (o campo antigo `resposta` também é aceito).

## Regras

- O código vale **10 minutos** e aceita **5 erros**; depois fica bloqueado (423).
- Reenviar: espera **60 s** entre envios, no máximo **5** reenvios. O código antigo deixa de valer.
- O banco guarda só o **hash** do código (HMAC com a `APP_KEY`).
- Esqueci a senha com e-mail sem conta: a resposta é igual, mas nenhum e-mail sai e nenhum código funciona.
- Se o Resend falhar: login e recuperação respondem 502 `email_nao_enviado`; o cadastro cria a conta mesmo assim (`email_enviado: false`) e a pessoa usa "reenviar".
- Log de autenticação: eventos `codigo_email` e `confirmar_email`, método "Código por e-mail".

## Configuração (Railway → Variables)

```
RESEND_API_KEY=re_xxxxxxxx
EMAIL_REMETENTE=EcoTech <onboarding@resend.dev>
```

Com `onboarding@resend.dev` o Resend só entrega para o e-mail dono da conta do Resend (modo de teste).
Para enviar para qualquer pessoa, verifique um domínio em resend.com/domains e use um remetente desse domínio.

Rode a migration no servidor: `php artisan migrate --force`.

## Contas de exemplo (seed)

As contas do seed usam e-mails `@....ecotech.example` (domínio reservado, que nunca recebe e-mail).
Para elas nenhum e-mail é enviado e o código é o `CODIGO_DEMO` do `.env` (ex.: `123456`).
Contas reais **nunca** aceitam o `CODIGO_DEMO`.

Popular o banco:

```
php artisan migrate --force
php artisan db:seed --force                          # 9 empresas, 34 Ecopontos, recompensas, master
php artisan db:seed --class=DemoSeeder --force       # 15 pessoas com 6 meses de histórico
```

No servidor (`APP_ENV=production`), o DemoSeeder só roda com `SEED_DEMO_PRODUCAO=true`.
