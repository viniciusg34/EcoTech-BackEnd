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

        $master = Pessoa::query()->where('email', mb_strtolower($c['email']))->first() ?? new Pessoa();
        $novo = ! $master->exists;

        $master->forceFill([
            'nome'              => $c['nome'],
            'email'             => mb_strtolower($c['email']),
            'login'             => mb_strtolower($c['login']),
            'cpf'               => preg_replace('/\D/', '', (string) $c['cpf']),
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
