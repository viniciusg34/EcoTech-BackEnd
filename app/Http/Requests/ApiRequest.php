<?php

namespace App\Http\Requests;

use App\Support\Texto;
use Illuminate\Foundation\Http\FormRequest;

/*
 * Base dos Form Requests da API.
 * Quem pode chamar cada rota já foi decidido no routes/api.php (auth + papel),
 * então authorize() é sempre true aqui.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Deixa só os dígitos dos campos informados (CPF, CEP, telefones...)
    protected function soDigitos(array $campos): void
    {
        $limpos = [];
        foreach ($campos as $campo) {
            if ($this->has($campo) && is_scalar($this->input($campo)) && $this->input($campo) !== null) {
                $limpos[$campo] = Texto::digitos((string) $this->input($campo));
            }
        }
        $this->merge($limpos);
    }

    // Tira espaços sobrando dos campos de texto
    protected function semEspacos(array $campos): void
    {
        $limpos = [];
        foreach ($campos as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $limpos[$campo] = Texto::limparEspacos($this->input($campo));
            }
        }
        $this->merge($limpos);
    }

    protected function minusculo(array $campos): void
    {
        $limpos = [];
        foreach ($campos as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $limpos[$campo] = mb_strtolower(trim($this->input($campo)));
            }
        }
        $this->merge($limpos);
    }

    // Telefone: aceita "(21) 99999-1234", "(+55)21-999991234"... e guarda "21999991234"
    protected function telefone(string $campo): void
    {
        if (! $this->filled($campo) || ! is_scalar($this->input($campo))) {
            return;
        }
        $digitos = Texto::digitos((string) $this->input($campo));
        if (str_starts_with($digitos, '55') && in_array(strlen($digitos), [12, 13], true)) {
            $digitos = substr($digitos, 2);
        }
        $this->merge([$campo => $digitos]);
    }
}
