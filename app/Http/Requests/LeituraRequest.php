<?php

namespace App\Http\Requests;

// POST /ecopontos/{id}/leituras  { sessao_id, code }   |   POST /scans  { code, scannedAt }
class LeituraRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => trim($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'sessao_id' => [$this->is('*/scans') ? 'sometimes' : 'required', 'integer'],
            'code'      => ['required', 'string', 'max:32'],
            'scannedAt' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }
}
