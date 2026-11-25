# 04 — Migração dos dados antigos (MockAPI)

Antes da API, os dados ficavam na MockAPI (`/pessoa`, `/empresa` e `/empresa/:id/ecopontos`) e depois foram copiados para o banco antigo no Aiven (`defaultdb`, tabelas `usuario`, `empresa`, `ecopontos` e `recompensas`). O comando `ecotech:importar-legado` traz os dois formatos para as tabelas novas.

## Regras da conversão

| Antes | Depois |
|---|---|
| Senha em texto puro | `senha_hash` com bcrypt (a pessoa continua entrando com a mesma senha). Senha que já era hash é mantida. |
| `coins` | O saldo final fica igual ao antigo. Se a pessoa tem `reciclado[]`, cada item vira uma coleta de check-in e um crédito; o que faltar (ou sobrar, se ela já tinha gastado) vira **um** movimento `ajuste` "Saldo importado do sistema antigo". |
| `reciclado[]` (`{ name, pts, icon, codigo, time }`) | Uma `coleta` do tipo `checkin` no Ecoponto do `codigo` (com `checkin_kg`) e um `movimento_coins` do tipo `checkin`. O item guarda só a hora, então a data usada é a do cadastro da pessoa. Item com Ecoponto desconhecido entra no ajuste. |
| `perfil: "master"` / `tipo_usuario: "admin"` | `perfil = 'master'` |
| CPF, CEP, telefones com máscara | Só dígitos. CPF inválido ou repetido fica vazio (a pessoa entra pelo e-mail). Telefone com +55 perde o 55. |
| `nascimento` "12/05/1990" ou "1990-05-12" | `DATE` (`1990-05-12`) |
| `sexo` "Feminino", "M"... | `feminino`, `masculino`, `outro` ou `nao_informar` |
| `login` | Mantido se tiver exatamente 6 letras e não estiver em uso; senão fica vazio (entra pelo e-mail). |
| Empresa sem CNPJ válido ou sem e-mail | Pulada (aparece nos avisos). CNPJ com dígito errado é importado com aviso. |
| Ecoponto (`code`, `cats`, `lat`, `lng`, `state`) | `codigo` em maiúsculas (ou `LEG-XXXXXX` se vier vazio), categorias pelas chaves (`papel`, `plastico`...), estado em sigla, segredo do QR novo. |
| E-mail, CNPJ ou código que já existem no banco novo | Pulados: dá para rodar de novo sem duplicar. |

Tudo roda numa transação: se der erro no meio, nada é gravado.

## Opção A: a partir dos JSON da MockAPI

1. Abra no navegador e salve cada resposta como arquivo (Ctrl+S) numa pasta, por exemplo `storage/app/mockapi`:
   - `https://6a38cde364a2d8269222d659.mockapi.io/pessoa` → `pessoa.json`
   - `https://6a386bef64a2d82692228142.mockapi.io/api/v1/empresa` → `empresa.json`
   - `https://6a386bef64a2d82692228142.mockapi.io/api/v1/ecopontos` → `ecopontos.json` (cada ponto precisa do `empresaId`)
2. Confira sem gravar:
   ```bash
   php artisan ecotech:importar-legado --mockapi=storage/app/mockapi --simular
   ```
3. Se a tabela e os avisos estiverem certos, rode de novo sem `--simular`.
4. Apague a pasta com os JSON depois (tem senhas em texto puro).

## Opção B: a partir do banco antigo (`defaultdb`)

1. No `.env`, preencha `LEGADO_DB_HOST`, `LEGADO_DB_PORT`, `LEGADO_DB_DATABASE=defaultdb`, `LEGADO_DB_USERNAME`, `LEGADO_DB_PASSWORD` e `LEGADO_MYSQL_ATTR_SSL_CA` (o banco novo continua em `DB_*`, separado).
2. `php artisan ecotech:importar-legado --simular`, confira, e rode sem `--simular`.

O comando só **lê** o banco antigo; nada é alterado lá.

## Depois de importar

- As pessoas importadas entram com a senha antiga. O 2FA sorteia entre nome da mãe, nascimento e CEP **que a pessoa tiver**; quem não tem nenhum dos três recebe `409 cadastro_incompleto` no login. Confira antes: `SELECT email FROM pessoa WHERE nome_mae IS NULL AND nascimento IS NULL AND cep IS NULL;`
- Senhas antigas fora da regra atual (senha forte) continuam valendo até a pessoa trocar.
- Confira um saldo: `GET /me/movimentos` da pessoa mostra os check-ins importados e o ajuste.
