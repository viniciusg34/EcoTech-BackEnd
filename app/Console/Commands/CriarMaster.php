<?php

namespace App\Console\Commands;

use App\Models\Pessoa;
use App\Rules\SenhaValida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/*
 * php artisan ecotech:criar-master
 * Cria (ou atualiza a senha de) a pessoa master usando os dados MASTER_* do .env.
 * A senha NUNCA fica no código: vem de MASTER_PASSWORD.
 */
class CriarMaster extends Command
{
    protected $signature = 'ecotech:criar-master';

    protected $description = 'Cria ou atualiza o usuário master a partir do .env (MASTER_*)';

    public function handle(): int
    {
        $c = config('ecotech.master');

        if (! $c['senha']) {
            $this->error('Defina MASTER_PASSWORD no .env antes de criar o master.');
            return self::FAILURE;
        }

        $validacao = Validator::make(['senha' => $c['senha']], ['senha' => [new SenhaValida()]]);
        if ($validacao->fails()) {
            $this->error('MASTER_PASSWORD inválida: ' . $validacao->errors()->first('senha'));
            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $c['email']));
        $login = mb_strtolower(trim((string) $c['login']));
        $cpf = preg_replace('/\D/', '', (string) $c['cpf']);

        // Só mexe em quem JÁ É master (pelo login ou pelo e-mail). Nunca transforma uma conta comum em master.
        $master = Pessoa::query()->where('perfil', 'master')->where('login', $login)->first()
            ?? Pessoa::query()->where('perfil', 'master')->where('email', $email)->first()
            ?? new Pessoa();
        $novo = ! $master->exists;

        // E-mail, login ou CPF do .env já usados por OUTRA conta: avisa e não altera nada
        $outra = fn (string $campo, string $valor) => Pessoa::query()
            ->where($campo, $valor)->when($master->exists, fn ($q) => $q->whereKeyNot($master->id))->first();
        $conflitos = array_filter([
            'MASTER_EMAIL' => ($p = $outra('email', $email)) ? "o e-mail {$email} já é da conta id {$p->id} ({$p->perfil})" : null,
            'MASTER_LOGIN' => ($p = $outra('login', $login)) ? "o login {$login} já é da conta id {$p->id} ({$p->perfil})" : null,
            'MASTER_CPF'   => ($p = $outra('cpf', $cpf)) ? "o CPF {$cpf} já é da conta id {$p->id} ({$p->perfil})" : null,
        ]);
        if ($conflitos) {
            foreach ($conflitos as $variavel => $motivo) {
                $this->error("{$variavel}: {$motivo}.");
            }
            $this->error('Master NÃO foi ' . ($novo ? 'criado' : 'atualizado') . '. Troque essa variável ou libere o dado na outra conta.');

            return self::FAILURE;
        }

        $master->forceFill([
            'nome'              => $c['nome'],
            'email'             => $email,
            'login'             => $login,
            'cpf'               => $cpf,
            'senha_hash'        => Hash::make($c['senha']),
            'nome_mae'          => $c['nome_mae'],
            'nascimento'        => $c['nascimento'],
            'sexo'              => 'nao_informar',
            'cep'               => preg_replace('/\D/', '', (string) $c['cep']),
            'perfil'            => 'master',
            'ativo'             => true,
            'termos_aceitos_em' => $master->termos_aceitos_em ?? now(),
        ])->save();

        $this->info(($novo ? 'Master criado: ' : 'Master atualizado: ') . $master->email . ' (login ' . $master->login . ')');

        return self::SUCCESS;
    }
}
