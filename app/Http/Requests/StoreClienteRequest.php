<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome'      => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email:rfc', Rule::unique('clientes', 'email')],  // dns lookup quebra em CI/ambientes isolados
            'documento' => [
                'required',
                'string',
                // Aceita CPF (11 dígitos) ou CNPJ (14 dígitos) — apenas dígitos, sem formatação
                'regex:/^\d{11}$|^\d{14}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documento.regex' => 'O documento deve ser um CPF (11 dígitos) ou CNPJ (14 dígitos) sem formatação.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome'      => 'nome',
            'email'     => 'e-mail',
            'documento' => 'documento (CPF/CNPJ)',
        ];
    }
}
