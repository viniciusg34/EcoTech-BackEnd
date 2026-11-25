# EcoTech — API (back-end)

API REST do **EcoTech**, plataforma de reciclagem do Rio de Janeiro e da Costa Verde: as pessoas ganham **EcoCoins** ao reciclar nos **Ecopontos** das empresas parceiras e trocam as moedas por recompensas.

Projeto da disciplina *Projeto de Desenvolvimento em Back-End* (ADS — UNISUAM). O front-end (HTML/JS) fica em outro repositório e conversa com esta API por JSON.

| | |
|---|---|
| Linguagem / framework | PHP 8.3+ com Laravel 13 |
| Banco | MySQL 8 (ou MariaDB do XAMPP); SQLite nos testes |
| Login | Laravel Sanctum (token Bearer) + pergunta de segurança (2FA) |
| Endereço base | `http://127.0.0.1:8000/api/v1` (local) · `https://SEU-DOMINIO/api/v1` (produção) |

## Documentação

| Arquivo | O que tem |
|---|---|
| [`docs/openapi.yaml`](docs/openapi.yaml) | Todas as rotas, com exemplo real de pedido e resposta. Abra em [editor.swagger.io](https://editor.swagger.io) (File > Import file). |
| [`docs/EcoTech.postman_collection.json`](docs/EcoTech.postman_collection.json) | Coleção para o Postman ou Insomnia (os tokens são guardados sozinhos depois do login). |
| [`docs/guia-migracao-front.md`](docs/guia-migracao-front.md) | Para o time do front: arquivo por arquivo, a chamada antiga (MockAPI, ViaCEP, Open Food Facts, Google) e a nova. |
| [`docs/decisoes-e-pendencias.md`](docs/decisoes-e-pendencias.md) | Decisões tomadas, respostas às perguntas do front e o que ainda depende do grupo. |
| [`docs/banco/der.md`](docs/banco/der.md) · [`docs/banco/dicionario-de-dados.md`](docs/banco/dicionario-de-dados.md) | DER (Mermaid) e dicionário de dados das 17 tabelas. |
| [`database/sql/`](database/sql/README.md) | SQL de referência (MySQL 8): tabelas, views, procedures, dados iniciais, testes e o guia de migração da MockAPI. |

## Rodar no seu computador

### 1. Pré-requisitos

- **PHP 8.3 ou mais novo** com as extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` e `curl` (o XAMPP 8.3 já vem com elas). Confira com `php -v`.
- **Composer** ([getcomposer.org](https://getcomposer.org)). Confira com `composer -V`.
- **MySQL ou MariaDB** (o do XAMPP serve). Sem banco instalado dá para usar SQLite (veja o `.env.example`).

### 2. Instalar

No terminal, dentro da pasta do projeto:

```bash
composer install
copy .env.example .env        # no Linux/Mac: cp .env.example .env
php artisan key:generate
```

### 3. Configurar o `.env`

Abra o `.env` e preencha pelo menos:

| Chave | O que colocar |
|---|---|
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | No XAMPP: crie o banco `ecotech` no phpMyAdmin (agrupamento `utf8mb4_unicode_ci`); usuário `root`, senha vazia. |
| `QR_SECRET` | Um texto longo e aleatório: `php -r "echo bin2hex(random_bytes(32));"`. Não troque depois (as placas impressas deixam de valer). |
| `MASTER_PASSWORD` | Senha do administrador. Segue a `SENHA_REGRA` (padrão **forte**: 8+ com maiúscula, minúscula e número), ex.: `Master123`. |
| `MASTER_NOME_MAE`, `MASTER_NASCIMENTO`, `MASTER_CEP` | Respostas da pergunta de segurança do master. |
| `CORS_ORIGINS` | Endereço do front publicado (a Vercel). O Live Server (`localhost`) já é liberado por `CORS_LOCALHOST=true`. |
| `GOOGLE_CLIENT_ID` | O mesmo Client ID do botão "Entrar com Google" do front (vazio = login com Google desligado). |

Se o `.env` for antigo, confira também: `CACHE_STORE=file`, `SESSION_DRIVER=file` e `QUEUE_CONNECTION=sync`.

### 4. Criar as tabelas e os dados iniciais

```bash
php artisan migrate --seed
```

Sem o Laravel, o mesmo banco sai dos scripts de `database/sql` (`01_schema.sql`, `02_views_procedures.sql` e `03_seed.sql`, que já cria o master com a senha inicial `Master123`, como pede o enunciado; troque depois).

O `migrate --seed` cria as 17 tabelas, as 6 categorias de material, as 11 recompensas, os 6 produtos da campanha, as configurações, 3 empresas fictícias com os 22 Ecopontos do mapa e o master. Pode rodar `php artisan db:seed` de novo sem duplicar nada.

Opcional, para a apresentação: pessoas com 6 meses de histórico (os painéis ganham gráficos de verdade):

```bash
php artisan db:seed --class=DemoSeeder
```

### 5. Ligar a API

```bash
php artisan serve
```

Teste no navegador: <http://127.0.0.1:8000/api/v1/estatisticas>. No front, o endereço base passa a ser `http://127.0.0.1:8000/api/v1`.

Para as tarefas automáticas (expirar sessões de Ecoponto e desafios de 2FA vencidos) rodarem enquanto você testa, deixe outro terminal aberto com:

```bash
php artisan schedule:work
```

### Contas para testar

| Conta | Login | Senha | Pergunta de segurança |
|---|---|---|---|
| Master | `MASTER` ou `master@ecotech.com` | `MASTER_PASSWORD` do `.env` (ou `Master123`, se o banco foi criado pelo `03_seed.sql`) | `MASTER_NOME_MAE`, `MASTER_NASCIMENTO` (dd/mm/aaaa) ou `MASTER_CEP` (no script SQL: Maria da Silva · 01/01/2000 · 20040-020) |
| Empresa (fictícia) | `contato@costaverde.ecotech.example` | `Ecotech2026` (`SEED_EMPRESA_PASSWORD`) | empresa não tem 2FA |
| Pessoa de demonstração | `anasil`, `brunof` ou `carlam` | `Reciclar1` (`SEED_DEMO_PASSWORD`) | Maria José da Silva · 12/05/1990 · 20040-020 |

As outras empresas fictícias: `contato@riorecicla.ecotech.example` e `contato@metroverde.ecotech.example` (mesma senha).

## Comandos do EcoTech

| Comando | Para quê |
|---|---|
| `php artisan ecotech:criar-master` | Cria o master com os dados `MASTER_*` do `.env`, ou atualiza a senha dele. Use quando trocar a senha do master. |
| `php artisan ecotech:expirar` | Expira sessões de Ecoponto e desafios de 2FA vencidos e apaga tokens vencidos. Roda sozinho a cada minuto pelo agendador. |
| `php artisan ecotech:importar-legado --simular` | Mostra o que seria importado do banco antigo (conexão `LEGADO_DB_*`). Sem `--simular`, importa. |
| `php artisan ecotech:importar-legado --mockapi=pasta` | Importa os JSON exportados da MockAPI (`pessoa.json`, `empresa.json`, `ecopontos.json`). Veja [`database/sql/04_migracao_mockapi.md`](database/sql/04_migracao_mockapi.md). |
| `php artisan db:seed --class=DemoSeeder` | Pessoas de demonstração com histórico (não roda em produção). |
| `php artisan test` | Roda os testes automáticos. |

## Testes automáticos

```bash
php artisan test
```

Os testes usam um banco SQLite na memória (não mexem no seu banco) e cobrem: CPF, CNPJ e login de 6 letras; cadastro com 409 e 422; 2FA com 3 tentativas e bloqueio; recuperação de senha sem revelar o e-mail; login com Google; idempotência do `/scans` e das leituras; resgate concorrente e saldo nunca negativo; autorização por papel (pessoa, empresa, master); LGPD (CPF mascarado e anonimização); rotas públicas, CORS e erros em JSON; importação dos dados antigos.

## O que tem em cada pasta

```
app/
  Http/Controllers/Api/V1/   controllers da API (Admin/ = área do master)
  Http/Requests/             validação de cada pedido (Form Requests)
  Http/Resources/            formato das respostas (já formatadas em pt-BR)
  Http/Middleware/           papel (pessoa/empresa/master), Idempotency-Key, JSON
  Services/                  regras de negócio: CoinService (livro-razão), AuthService,
                             TwoFactorService, ScanService, EcopontoQrService...
  Clients/                   Open Food Facts, ViaCEP e Google (chamadas externas)
  Models/                    uma classe por tabela
  Rules/                     Cpf, Cnpj, LoginSeisLetras, SenhaValida
  Console/Commands/          comandos ecotech:*
database/
  migrations/                as tabelas (fonte da verdade do banco)
  seeders/                   dados iniciais e de demonstração
  sql/                       SQL de referência (MySQL 8) e testes do banco
routes/api.php               todas as rotas /api/v1
docs/                        OpenAPI, Postman, guias e DER
tests/                       testes automáticos (PHPUnit)
```

## Publicar (deploy)

Em produção a API precisa de **HTTPS** (a Vercel bloqueia chamadas HTTP) e do `.env` de produção:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.seudominio.com.br
CORS_ORIGINS=https://eco-tech-blush.vercel.app
CORS_LOCALHOST=false
```

### Hospedagem compartilhada (com acesso SSH)

1. Envie o projeto (sem a pasta `vendor` e sem o `.env`) para uma pasta **fora** da `public_html`, por exemplo `~/ecotech`.
2. Pelo SSH, dentro de `~/ecotech`:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env      # e preencha com os dados de produção
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan config:cache
   php artisan route:cache
   chmod -R 775 storage bootstrap/cache
   ```
3. Faça o domínio (ou subdomínio, ex.: `api.seudominio.com.br`) apontar para `~/ecotech/public`. Se o painel não deixar escolher a pasta, copie o **conteúdo** de `public/` (inclusive o `.htaccess`) para `public_html/` e troque os três caminhos do `public_html/index.php`:
   ```php
   if (file_exists($maintenance = __DIR__.'/../ecotech/storage/framework/maintenance.php')) {
       require $maintenance;
   }
   require __DIR__.'/../ecotech/vendor/autoload.php';
   (require_once __DIR__.'/../ecotech/bootstrap/app.php')->handleRequest(Request::capture());
   ```
4. Ative o HTTPS no painel (Let's Encrypt).
5. Crie a tarefa agendada (cron) no painel, a cada minuto:
   ```
   * * * * * cd ~/ecotech && php artisan schedule:run >> /dev/null 2>&1
   ```
6. Depois de mudar o `.env` em produção, rode `php artisan config:cache` de novo.

### VPS (Ubuntu + Nginx + PHP-FPM + MySQL)

```bash
sudo apt install nginx mysql-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath unzip
# instale o Composer (getcomposer.org) e envie o projeto para /var/www/ecotech
cd /var/www/ecotech
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env && php artisan key:generate
php artisan migrate --force && php artisan db:seed --force
php artisan config:cache && php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

`/etc/nginx/sites-available/ecotech`:

```nginx
server {
    listen 80;
    server_name api.seudominio.com.br;
    root /var/www/ecotech/public;
    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Depois: `sudo ln -s /etc/nginx/sites-available/ecotech /etc/nginx/sites-enabled/`, `sudo nginx -t && sudo systemctl reload nginx`, HTTPS com `sudo certbot --nginx -d api.seudominio.com.br` e o cron (`crontab -e -u www-data`) com a mesma linha do `schedule:run`.

### Banco no Aiven (MySQL na nuvem)

- Crie um banco **só do EcoTech** (ex.: `ecotech`) na aba *Databases* do serviço; não use o `defaultdb`, que tem as tabelas antigas.
- No `.env`: `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` do painel, `DB_DATABASE=ecotech` e `MYSQL_ATTR_SSL_CA` com o caminho do certificado CA baixado do painel.
- Rode `php artisan migrate --seed` apontando para ele.

## Segurança

- Senhas sempre com `Hash::make` (bcrypt); `senha_hash` nunca aparece nas respostas.
- Token Bearer com validade (8 h, ou 30 dias com "lembrar"), revogado no logout, na troca de senha e quando a conta é suspensa ou excluída.
- Limite de tentativas: login 5 a cada 15 minutos por IP e conta; 2FA com 3 tentativas por desafio; leituras 30 por minuto.
- O cliente nunca manda moedas nem saldo: o servidor calcula tudo, e todo crédito ou débito passa pelo livro-razão com a linha da pessoa travada (saldo nunca negativo).
- QR dos Ecopontos assinado com HMAC-SHA256 (`QR_SECRET` + segredo de cada ponto).
- LGPD: as listas mostram CPF mascarado e nunca nome da mãe, nascimento ou CEP; excluir uma conta anonimiza os dados e mantém o histórico.
- Nunca envie o `.env` para o GitHub.
