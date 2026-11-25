<?php

namespace App\Http\Requests;

use App\Support\Texto;
use Closure;
use Illuminate\Validation\Rule;

// Master: criar (POST) / editar (PUT e PATCH) produto da campanha
class ProdutoRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->semEspacos(['nome']);
        if ($this->has('codigo_barras') && is_scalar($this->input('codigo_barras'))) {
            $this->merge(['codigo_barras' => trim((string) $this->input('codigo_barras'))]);
        }
    }

    public function rules(): array
    {
        $criando = $this->isMethod('POST');
        $obrigatorio = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'codigo_barras' => $criando ? ['required', 'string', function (string $campo, mixed $valor, Closure $falhar) {
                if (! Texto::codigoBarrasValido((string) $valor)) {
                    $falhar('Código de barras inválido (confira o dígito verificador).');
                }
            }, Rule::unique('produto_catalogo', 'codigo_barras')] : ['prohibited'],
            'nome'      => [...$obrigatorio, 'string', 'max:150'],
            'categoria' => [...$obrigatorio, 'string', 'exists:categoria_material,chave'],
            'coins'     => [...$obrigatorio, 'integer', 'min:0', 'max:100000'],
            'icone'     => ['sometimes', 'nullable', 'string', 'max:10'],
            'peso_kg'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'ativo'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_barras.unique'     => 'Este código de barras já está no catálogo.',
            'codigo_barras.prohibited' => 'O código de barras não pode ser alterado.',
        ];
    }
}
