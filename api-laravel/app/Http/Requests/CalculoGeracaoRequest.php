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
            // Geração bruta do mês: aceita o nome novo OU o legado. Pelo menos um
            // deve estar presente (validado em withValidator, abaixo).
            'geracao_bruta_kwh' => 'nullable|numeric|min:0',
            'mesGeracao_kwh' => 'nullable|numeric|min:0',

            // Parâmetros antes enviados pelo front; agora derivados da usina no
            // backend. Mantidos opcionais para compat (o novo fluxo os ignora).
            'mediaGeracao_kwh' => 'nullable|numeric|min:0',
            'reservaTotalAnterior_kwh' => 'nullable|numeric|min:0',
            'tarifa_kwh' => 'nullable|numeric|min:0',

            // valorPago_mes (legado) é IGNORADO no novo fluxo — o valor final agora
            // é CALCULADO pelo núcleo. Mantido nullable só para não quebrar o front
            // atual, que ainda o envia.
            'valorPago_mes' => 'nullable|numeric|min:0',

            // Novos campos opcionais (REGRAS_DE_CALCULO.md §5, §9).
            'consumo' => 'nullable|numeric|min:0',
            'fatura_energia' => 'nullable|numeric|min:0',
            'adicional_cuo' => 'nullable|numeric',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bruta = $this->input('geracao_bruta_kwh', $this->input('mesGeracao_kwh'));

            if ($bruta === null) {
                $validator->errors()->add(
                    'geracao_bruta_kwh',
                    'Informe a geração bruta do mês (geracao_bruta_kwh ou mesGeracao_kwh).'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 400));
    }
}
