<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Login com exatamente 6 letras (acentuadas valem), sem números nem espaços
class LoginSeisLetras implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\p{L}{6}$/u', $value)) {
            $fail('O login deve ter exatamente 6 letras.');
        }
    }
}
