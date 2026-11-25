<?php

namespace App\Http\Requests;

use App\Rules\Cpf;
use App\Rules\LoginSeisLetras;
use App\Rules\SenhaValida;
use App\Support\Texto;

/*
 * POST /pessoas — cadastro. Regras do enunciado + do documento do front.
 */
class CadastrarPessoaRequest extends ApiRequest
{
    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE',
        'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome', 'nome_mae', 'logradouro', 'numero', 'bairro', 'cidade']);
        $this->minusculo(['email', 'login']);
        $this->soDigitos(['cpf', 'cep']);
        $this->telefone('telefone_celular');
        $this->telefone('telefone_fixo');

        if ($this->filled('uf') && is_string($this->input('uf'))) {
            $this->merge(['uf' => mb_strtoupper(trim($this->input('uf')))]);
        }
        if ($this->filled('nascimento') && is_string($this->input('nascimento'))) {
            $this->merge(['nascimento' => Texto::data($this->input('nascimento')) ?? $this->input('nascimento')]);
        }
        if ($this->filled('sexo') && is_string($this->input('sexo'))) {
            $mapa = ['f' => 'feminino', 'm' => 'masculino', 'o' => 'outro', 'n' => 'nao_informar', 'nao informar' => 'nao_informar'];
            $sexo = Texto::normalizar($this->input('sexo'));
            $this->merge(['sexo' => $mapa[$sexo] ?? str_replace(' ', '_', $sexo)]);
        }
        // Campo vazio de telefone fixo (opcional) vira null
        if ($this->has('telefone_fixo') && $this->input('telefone_fixo') === '') {
            $this->merge(['telefone_fixo' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'nome'             => ['required', 'string', 'between:15,80', "regex:/^[\p{L}' -]+$/u"],
            'email'            => ['required', 'string', 'email', 'max:255'],
            'cpf'              => ['required', 'string', new Cpf()],
            'login'            => ['required', 'string', new LoginSeisLetras()],
            'senha'            => ['required', 'string', new SenhaValida()],
            'confirmar_senha'  => ['sometimes', 'nullable', 'same:senha'],
            'nome_mae'         => ['required', 'string', 'between:3,150', "regex:/^[\p{L}' -]+$/u"],
            'nascimento'       => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'sexo'             => ['required', 'in:feminino,masculino,outro,nao_informar'],
            'telefone_celular' => ['required', 'string', 'regex:/^\d{2}9\d{8}$/'],
            'telefone_fixo'    => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'cep'              => ['required', 'string', 'digits:8'],
            'logradouro'       => ['required', 'string', 'max:150'],
            'numero'           => ['required', 'string', 'max:20'],
            'bairro'           => ['required', 'string', 'max:100'],
            'cidade'           => ['required', 'string', 'max:100'],
            'uf'               => ['required', 'string', 'in:' . implode(',', self::UFS)],
            'termos_aceitos'   => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.between'           => 'O nome deve ter entre 15 e 80 caracteres.',
            'nome.regex'             => 'O nome deve ter apenas letras.',
            'nome_mae.regex'         => 'O nome da mãe deve ter apenas letras.',
            'nascimento.date_format' => 'Informe a data de nascimento no formato dd/mm/aaaa.',
            'nascimento.before'      => 'A data de nascimento não pode ser no futuro.',
            'telefone_celular.regex' => 'O celular deve ter DDD + 9 dígitos, no formato (XX) XXXXX-XXXX.',
            'telefone_fixo.regex'    => 'O telefone fixo deve ter DDD + 8 dígitos, no formato (XX) XXXX-XXXX.',
            'confirmar_senha.same'   => 'As senhas não coincidem.',
            'termos_aceitos.accepted' => 'Você precisa aceitar os Termos e Condições.',
            'sexo.in'                => 'Escolha uma opção de sexo.',
            'uf.in'                  => 'UF inválida.',
        ];
    }
}
