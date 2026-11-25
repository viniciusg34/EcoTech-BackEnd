<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/*
 * REGRA DA SENHA
 *   new SenhaValida()          -> pessoa: segue config('ecotech.senha_regra')
 *   new SenhaValida('forte')   -> empresa: sempre senha forte
 *
 *   forte     = mínimo 8 caracteres, com maiúscula, minúscula e número (padrão, igual ao front)
 *   enunciado = exatamente 8 letras (regra do PDF da disciplina; SENHA_REGRA=enunciado)
 */
class SenhaValida implements ValidationRule
{
    public function __construct(private ?string $regra = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $regra = $this->regra ?? config('ecotech.senha_regra', 'forte');
        $senha = is_string($value) ? $value : '';

        if ($regra === 'forte') {
            $ok = mb_strlen($senha) >= 8 && mb_strlen($senha) <= 72
                && preg_match('/\p{Lu}/u', $senha) && preg_match('/\p{Ll}/u', $senha) && preg_match('/\d/', $senha);
            if (! $ok) {
                $fail('A senha precisa ter 8 ou mais caracteres, com letra maiúscula, minúscula e número.');
            }
            return;
        }

        if (! preg_match('/^\p{L}{8}$/u', $senha)) {
            $fail('A senha deve ter exatamente 8 letras.');
        }
    }
}
