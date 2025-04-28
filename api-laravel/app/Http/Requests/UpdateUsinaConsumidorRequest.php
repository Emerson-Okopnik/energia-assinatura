<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUsinaConsumidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usi_id'    => 'required|integer|exists:usina,usi_id',
            'cli_id'    => 'required|integer|exists:cliente,cli_id',
            'con_ids'   => 'required|array|min:1',
            'con_ids.*' => 'integer|exists:consumidor,con_id',
        ];
    }

    public function messages(): array
    {
        return [
            'usi_id.required'        => 'O ID da usina é obrigatório.',
            'usi_id.integer'         => 'O ID da usina deve ser um número inteiro.',
            'usi_id.exists'          => 'A usina informada não existe.',

            'cli_id.required'        => 'O ID do cliente é obrigatório.',
            'cli_id.integer'         => 'O ID do cliente deve ser um número inteiro.',
            'cli_id.exists'          => 'O cliente informado não existe.',

            'con_ids.required'       => 'A lista de consumidores é obrigatória.',
            'con_ids.array'          => 'A lista de consumidores deve ser um array.',
            'con_ids.min'            => 'Você deve informar pelo menos um consumidor.',
            'con_ids.*.integer'      => 'Todos os consumidores devem ser números inteiros.',
            'con_ids.*.exists'       => 'O consumidor selecionado não existe.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();

        // Substitui a mensagem padrão pela versão com ID personalizado
        if (isset($errors['con_ids'])) {
            foreach ($this->input('con_ids', []) as $index => $id) {
                if (isset($errors["con_ids.$index"])) {
                    $errors["con_ids.$index"] = ["O consumidor de ID $id não existe."];
                }
            }
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Um ou mais consumidores informados são inválidos.',
            'errors'  => $errors,
        ], 422));
    }
}
