<?php

namespace App\Console\Commands;

use App\Models\CategoriaMaterial;
use App\Models\Coleta;
use App\Models\Configuracao;
use App\Models\Ecoponto;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\Recompensa;
use App\Services\CoinService;
use App\Support\Texto;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/*
 * php artisan ecotech:importar-legado [--mockapi=PASTA] [--simular]
 * ---------------------------------------------------------------
 * Copia os dados antigos para as tabelas novas. Duas fontes possíveis:
 *   - sem opção: o banco ANTIGO (conexão "legado", tabelas usuario, empresa,
 *     ecopontos e recompensas, que vieram da MockAPI)
 *   - --mockapi=PASTA: os JSON exportados da MockAPI (pessoa.json,
 *     empresa.json e ecopontos.json, salvos abrindo as URLs no navegador)
 * Regras:
 *   - senha em texto puro vira hash (quem já tinha hash continua igual)
 *   - cada item de reciclado[] (só MockAPI) vira uma coleta de check-in + um
 *     movimento de moedas; o que faltar para chegar ao saldo antigo vira UM
 *     movimento "ajuste" (o saldo final é igual ao antigo)
 *   - tipo_usuario "admin" ou perfil "master" vira perfil "master"
 *   - e-mail, CNPJ ou código de Ecoponto que já existem no banco novo são pulados
 * Com --simular nada é gravado: só mostra o que seria feito.
 */
class ImportarLegado extends Command
{
    protected $signature = 'ecotech:importar-legado
        {--mockapi= : Pasta com pessoa.json, empresa.json e ecopontos.json exportados da MockAPI}
        {--simular : Só mostra o que seria importado, sem gravar}';

    protected $description = 'Importa pessoas, empresas, Ecopontos e recompensas do sistema antigo (banco "legado" ou JSON da MockAPI)';

    private ?string $pasta = null;

    private array $resumo = [];

    private array $avisos = [];

    private array $empresaPorIdAntigo = [];

    public function handle(CoinService $coins): int
    {
        if ($this->option('mockapi')) {
            $this->pasta = rtrim((string) $this->option('mockapi'), '/\\');
            if (! is_dir($this->pasta)) {
                $this->error("Pasta não encontrada: {$this->pasta}");
                return self::FAILURE;
            }
        } else {
            try {
                DB::connection('legado')->getPdo();
            } catch (Throwable $e) {
                $this->error('Não consegui conectar no banco antigo (LEGADO_DB_* no .env): ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $simular = (bool) $this->option('simular');
        $this->info(($simular ? 'SIMULAÇÃO: nada será gravado. ' : 'Importando ')
            . ($this->pasta ? "dos JSON da MockAPI em {$this->pasta}" : 'do banco antigo (conexão "legado")'));

        DB::beginTransaction();
        try {
            $this->empresas();
            $this->ecopontos();
            $this->recompensas();
            $this->pessoas($coins);
            $simular ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Erro, nada foi gravado: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Tabela', 'Importados', 'Pulados'], collect($this->resumo)->map(
            fn ($r, $tabela) => [$tabela, $r['importados'], $r['pulados']]
        )->values()->all());

        foreach (array_slice($this->avisos, 0, 50) as $aviso) {
            $this->warn($aviso);
        }
        if (count($this->avisos) > 50) {
            $this->warn('... e mais ' . (count($this->avisos) - 50) . ' avisos.');
        }

        return self::SUCCESS;
    }

    private function contar(string $tabela, bool $importou, ?string $aviso = null): void
    {
        $this->resumo[$tabela] ??= ['importados' => 0, 'pulados' => 0];
        $this->resumo[$tabela][$importou ? 'importados' : 'pulados']++;
        if ($aviso) {
            $this->avisos[] = "[{$tabela}] {$aviso}";
        }
    }

    // Linhas de uma tabela antiga, sempre no formato das tabelas do banco antigo
    private function linhas(string $tabela): array
    {
        if ($this->pasta !== null) {
            return $this->linhasMockApi($tabela);
        }

        return Schema::connection('legado')->hasTable($tabela)
            ? DB::connection('legado')->table($tabela)->get()->map(fn ($l) => (array) $l)->all()
            : [];
    }

    // Lê o JSON da MockAPI e converte os nomes de campo (camelCase) para os do banco antigo
    private function linhasMockApi(string $tabela): array
    {
        $arquivo = ['empresa' => 'empresa.json', 'ecopontos' => 'ecopontos.json', 'usuario' => 'pessoa.json'][$tabela] ?? null;
        if (! $arquivo || ! is_file($caminho = $this->pasta . DIRECTORY_SEPARATOR . $arquivo)) {
            return [];
        }

        $itens = json_decode((string) file_get_contents($caminho), true);
        if (! is_array($itens) || ! array_is_list($itens)) {
            $this->avisos[] = "[{$arquivo}] não é uma lista JSON, ignorado.";
            return [];
        }

        $v = fn (array $i, string ...$chaves) => collect($chaves)->map(fn ($c) => $i[$c] ?? null)->first(fn ($x) => $x !== null && $x !== '');

        return collect($itens)->filter(fn ($i) => is_array($i))->map(fn (array $i) => match ($tabela) {
            'empresa' => [
                'empre_id' => $v($i, 'id'),
                'name'     => $v($i, 'nome', 'name', 'razaoSocial'),
                'email'    => $v($i, 'email'),
                'password' => $v($i, 'senha', 'password'),
                'cnpj'     => $v($i, 'cnpj'),
                'adress'   => $v($i, 'endereco', 'address'),
            ],
            'ecopontos' => [
                'eco_id'   => $v($i, 'id'),
                'name'     => $v($i, 'nome', 'name'),
                'code'     => $v($i, 'codigo', 'code'),
                'adress'   => $v($i, 'endereco', 'address'),
                'city'     => $v($i, 'cidade', 'city'),
                'state'    => $v($i, 'estado', 'uf', 'state'),
                'cats'     => is_array($c = $v($i, 'categorias', 'cats')) ? implode(',', $c) : $c,
                'empre_id' => $v($i, 'empresaId', 'empresa_id', 'empre_id'),
                'status'   => in_array(Texto::normalizar((string) ($i['status'] ?? 'ativo')), ['inativo', '0', 'false'], true) ? 0 : 1,
                'lat'      => $v($i, 'lat', 'latitude'),
                'lng'      => $v($i, 'lng', 'longitude'),
            ],
            'usuario' => [
                'user_id'          => $v($i, 'id'),
                'first_name'       => $v($i, 'nome', 'name'),
                'last_name'        => '',
                'birth_day'        => $v($i, 'nascimento'),
                'sex'              => $v($i, 'sexo'),
                'mothers_fullname' => $v($i, 'nomeMae', 'nome_mae'),
                'cpf'              => $v($i, 'cpf'),
                'email'            => $v($i, 'email'),
                'login'            => $v($i, 'login'),
                'phone_number'     => $v($i, 'telefoneCelular', 'telefone_celular'),
                'landline_number'  => $v($i, 'telefoneFixo', 'telefone_fixo'),
                'adress'           => $v($i, 'endereco'),
                'password'         => $v($i, 'senha', 'password'),
                'avatar'           => $v($i, 'avatar'),
                'reg_date'         => $v($i, 'createdAt', 'criadoEm'),
                'cep'              => $v($i, 'cep'),
                'tipo_usuario'     => ($i['perfil'] ?? '') === 'master' ? 'admin' : 'comum',
                'coins'            => $v($i, 'coins') ?? 0,
                'reciclado'        => is_array($i['reciclado'] ?? null) ? $i['reciclado'] : [],
            ],
        })->values()->all();
    }

    // Senha antiga: se já é hash, mantém; se é texto puro, criptografa; se não tem, cria uma aleatória
    private function senha(?string $antiga): string
    {
        if ($antiga && password_get_info($antiga)['algo'] !== null) {
            return $antiga;
        }
        return Hash::make($antiga ?: Str::random(32));
    }

    // "1990-05-12", "1990-05-12T00:00:00Z" ou "12/05/1990" -> "1990-05-12"
    private function dataAntiga(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : (Texto::data(substr($valor, 0, 10)) ?? Texto::data($valor));
    }

    private function momentoAntigo(mixed $valor): Carbon
    {
        if (is_numeric($valor) && (int) $valor > 0) {
            return Carbon::createFromTimestamp((int) $valor > 9999999999 ? intdiv((int) $valor, 1000) : (int) $valor);
        }
        $valor = trim((string) $valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            return Carbon::parse($valor);
        }
        $data = $this->dataAntiga($valor);
        return $data ? Carbon::parse($data) : now();
    }

    private function empresas(): void
    {
        foreach ($this->linhas('empresa') as $l) {
            $cnpj = Texto::digitos($l['cnpj'] ?? '');
            $email = mb_strtolower(trim((string) ($l['email'] ?? '')));

            if (strlen($cnpj) !== 14 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->contar('empresa', false, "CNPJ ou e-mail inválido: {$email}");
                continue;
            }
            $existente = Empresa::query()->where('cnpj', $cnpj)->orWhere('email', $email)->first();
            if ($existente || Pessoa::query()->where('email', $email)->exists()) {
                if ($existente) {
                    $this->empresaPorIdAntigo[(string) $l['empre_id']] = $existente->id;
                }
                $this->contar('empresa', false, "já existe: {$email}");
                continue;
            }
            if (! Texto::cnpjValido($cnpj)) {
                $this->avisos[] = "[empresa] CNPJ com dígito verificador errado, importado mesmo assim: {$cnpj}";
            }

            $empresa = new Empresa();
            $empresa->forceFill([
                'cnpj'         => $cnpj,
                'nome_empresa' => mb_substr(trim((string) ($l['name'] ?? '')) ?: 'Empresa ' . $cnpj, 0, 150),
                'email'        => $email,
                'senha_hash'   => $this->senha($l['password'] ?? null),
                'endereco'     => isset($l['adress']) ? mb_substr((string) $l['adress'], 0, 255) : null,
                'status'       => 'ativa',
            ])->save();

            $this->empresaPorIdAntigo[(string) $l['empre_id']] = $empresa->id;
            $this->contar('empresa', true);
        }
    }

    private function ecopontos(): void
    {
        $categorias = CategoriaMaterial::query()->pluck('id', 'chave');
        $estados = ['rio de janeiro' => 'RJ', 'sao paulo' => 'SP', 'minas gerais' => 'MG', 'espirito santo' => 'ES'];

        foreach ($this->linhas('ecopontos') as $l) {
            $empresaId = $this->empresaPorIdAntigo[(string) ($l['empre_id'] ?? '')] ?? null;
            $codigo = mb_strtoupper(trim((string) ($l['code'] ?? '')));

            if (! $empresaId || ! is_numeric($l['lat'] ?? null) || ! is_numeric($l['lng'] ?? null)) {
                $this->contar('ecoponto', false, "sem empresa importada ou sem coordenadas: {$codigo}");
                continue;
            }
            if ($codigo === '' || ! preg_match('/^[A-Z0-9-]{1,20}$/', $codigo)) {
                $codigo = 'LEG-' . strtoupper(substr(md5((string) $l['eco_id']), 0, 6));
            }
            if (Ecoponto::query()->where('codigo', $codigo)->exists()) {
                $this->contar('ecoponto', false, "código já existe: {$codigo}");
                continue;
            }

            $estado = trim((string) ($l['state'] ?? ''));
            $estado = strlen($estado) === 2 ? strtoupper($estado) : ($estados[Texto::normalizar($estado)] ?? null);

            $ecoponto = new Ecoponto();
            $ecoponto->forceFill([
                'empresa_id' => $empresaId,
                'codigo'     => $codigo,
                'nome'       => mb_substr(trim((string) ($l['name'] ?? '')) ?: $codigo, 0, 150),
                'endereco'   => isset($l['adress']) ? mb_substr((string) $l['adress'], 0, 255) : null,
                'cidade'     => isset($l['city']) ? mb_substr((string) $l['city'], 0, 100) : null,
                'estado'     => $estado,
                'lat'        => max(-90, min(90, (float) $l['lat'])),
                'lng'        => max(-180, min(180, (float) $l['lng'])),
                'status'     => ! empty($l['status']) ? 'ativo' : 'inativo',
                'segredo_qr' => bin2hex(random_bytes(32)),
            ])->save();

            // "metal, papel" / "plástico;vidro" -> chaves das categorias
            $chaves = collect(preg_split('/[,;\/|]+/', (string) ($l['cats'] ?? '')))
                ->map(fn ($c) => str_replace(' ', '', Texto::normalizar($c)))
                ->filter(fn ($c) => isset($categorias[$c]))->unique();
            $ecoponto->categorias()->sync($chaves->map(fn ($c) => $categorias[$c])->values()->all());

            $this->contar('ecoponto', true);
        }
    }

    private function recompensas(): void
    {
        foreach ($this->linhas('recompensas') as $l) {
            $nome = trim((string) ($l['name'] ?? ''));
            $custo = (int) ($l['cost'] ?? 0);
            if ($nome === '' || $custo <= 0 || Recompensa::query()->where('nome', $nome)->exists()) {
                $this->contar('recompensa', false, $nome === '' ? 'sem nome' : "já existe ou sem custo: {$nome}");
                continue;
            }
            $categoria = in_array($l['cat'] ?? '', ['food', 'transport', 'health', 'shop', 'leisure'], true) ? $l['cat'] : 'shop';
            Recompensa::query()->create([
                'categoria'   => $categoria,
                'icone'       => isset($l['icon']) ? mb_substr((string) $l['icon'], 0, 10) : null,
                'nome'        => mb_substr($nome, 0, 150),
                'descricao'   => isset($l['desc_text']) ? mb_substr((string) $l['desc_text'], 0, 255) : null,
                'custo_coins' => $custo,
                'ativa'       => true,
            ]);
            $this->contar('recompensa', true);
        }
    }

    private function pessoas(CoinService $coins): void
    {
        $sexos = ['m' => 'masculino', 'masculino' => 'masculino', 'f' => 'feminino', 'feminino' => 'feminino',
            'o' => 'outro', 'outro' => 'outro', 'nao_informar' => 'nao_informar', 'prefiro nao informar' => 'nao_informar'];
        $kgCheckin = Configuracao::decimal('checkin_kg', 0.5);

        foreach ($this->linhas('usuario') as $l) {
            $email = mb_strtolower(trim((string) ($l['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->contar('pessoa', false, "e-mail inválido: {$email}");
                continue;
            }
            if (Pessoa::query()->where('email', $email)->exists() || Empresa::query()->where('email', $email)->exists()) {
                $this->contar('pessoa', false, "já existe: {$email}");
                continue;
            }

            $cpf = Texto::digitos($l['cpf'] ?? '');
            if (! Texto::cpfValido($cpf) || Pessoa::query()->where('cpf', $cpf)->exists()) {
                $this->avisos[] = "[pessoa] CPF inválido ou repetido, importado sem CPF: {$email}";
                $cpf = null;
            }

            $login = mb_strtolower(trim((string) ($l['login'] ?? '')));
            if (! preg_match('/^\p{L}{6}$/u', $login) || Pessoa::query()->where('login', $login)->exists()) {
                $login = null; // entra pelo e-mail
            }

            $telefone = Texto::digitos($l['phone_number'] ?? '');
            if (str_starts_with($telefone, '55') && strlen($telefone) >= 12) {
                $telefone = substr($telefone, 2);
            }
            $fixo = Texto::digitos($l['landline_number'] ?? '');
            $cep = Texto::digitos($l['cep'] ?? '');
            $registro = ! empty($l['reg_date']) ? $this->momentoAntigo($l['reg_date']) : now();
            $avatar = (string) ($l['avatar'] ?? '');

            $pessoa = new Pessoa();
            $pessoa->forceFill([
                'nome'              => mb_substr(Texto::limparEspacos(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?: 'Pessoa importada', 0, 80),
                'email'             => $email,
                'cpf'               => $cpf,
                'login'             => $login,
                'senha_hash'        => $this->senha($l['password'] ?? null),
                'nome_mae'          => ! empty($l['mothers_fullname']) ? mb_substr((string) $l['mothers_fullname'], 0, 150) : null,
                'nascimento'        => $this->dataAntiga($l['birth_day'] ?? null),
                'sexo'              => $sexos[Texto::normalizar((string) ($l['sex'] ?? ''))] ?? null,
                'telefone_celular'  => strlen($telefone) === 11 ? $telefone : null,
                'telefone_fixo'     => strlen($fixo) === 10 ? $fixo : null,
                'cep'               => strlen($cep) === 8 ? $cep : null,
                'logradouro'        => ! empty($l['adress']) ? mb_substr((string) $l['adress'], 0, 150) : null,
                'perfil'            => ($l['tipo_usuario'] ?? 'comum') === 'admin' ? 'master' : 'comum',
                'avatar_url'        => str_starts_with($avatar, 'http') && strlen($avatar) <= 400 ? $avatar : null,
                'saldo_coins'       => 0,
                'termos_aceitos_em' => $registro,
                'ativo'             => true,
            ]);
            $pessoa->criado_em = $registro;
            $pessoa->save();

            // reciclado[] (MockAPI): cada item vira uma coleta de check-in + um crédito
            $creditado = 0;
            foreach (array_reverse($l['reciclado'] ?? []) as $item) {
                $pts = is_array($item) ? (int) floor((float) ($item['pts'] ?? 0)) : 0;
                $codigo = is_array($item) ? mb_strtoupper(trim((string) ($item['codigo'] ?? ''))) : '';
                $ecoponto = $codigo !== '' ? Ecoponto::query()->where('codigo', $codigo)->first() : null;
                if ($pts <= 0 || ! $ecoponto) {
                    $this->avisos[] = "[reciclado] item sem Ecoponto conhecido ({$codigo}), somado ao ajuste: {$email}";
                    continue;
                }
                $coleta = new Coleta();
                $coleta->forceFill([
                    'pessoa_id' => $pessoa->id, 'ecoponto_id' => $ecoponto->id, 'sessao_id' => null,
                    'tipo' => 'checkin', 'total_coins' => $pts, 'total_kg' => $kgCheckin,
                ]);
                $coleta->criado_em = $registro;
                $coleta->save();
                $coins->creditar($pessoa->id, $pts, 'checkin', "Check-in em {$ecoponto->nome} (importado)", 'coleta', $coleta->id, $ecoponto->codigo, '♻️');
                $creditado += $pts;
                $this->contar('coleta', true);
            }

            // O que faltar (ou sobrar) para chegar ao saldo antigo vira UM ajuste
            $diferenca = (int) floor((float) ($l['coins'] ?? 0)) - $creditado;
            if ($diferenca > 0) {
                $coins->creditar($pessoa->id, $diferenca, 'ajuste', 'Saldo importado do sistema antigo', 'importacao', null, 'Migração', '⚙️');
            } elseif ($diferenca < 0) {
                $coins->debitar($pessoa->id, -$diferenca, 'ajuste', 'Moedas já usadas no sistema antigo', 'importacao', null, 'Migração', '⚙️');
            }

            $this->contar('pessoa', true);
        }
    }
}
