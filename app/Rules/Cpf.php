<?php

namespace App\Rules;

use App\Support\Texto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// CPF com os dígitos verificadores certos
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Texto::cpfValido($value)) {
            $fail('CPF inválido.');
        }
    }
}
