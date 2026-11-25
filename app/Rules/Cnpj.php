<?php

namespace App\Rules;

use App\Support\Texto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// CNPJ com os dígitos verificadores certos
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Texto::cnpjValido($value)) {
            $fail('CNPJ inválido.');
        }
    }
}
