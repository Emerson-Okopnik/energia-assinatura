<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CalculoGeracaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mesGeracao_kwh' => 'required|numeric|min:0',
            'mediaGeracao_kwh' => 'required|numeric|min:0',
            'reservaTotalAnterior_kwh' => 'required|numeric|min:0',
            'tarifa_kwh' => 'required|numeric|min:0',
            'valorPago_mes' => 'required|numeric|min:0',
            'adicional_cuo' => 'nullable|numeric',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 400));
    }
}
