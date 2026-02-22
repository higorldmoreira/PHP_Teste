<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PropostaOrigemEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePropostaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validações de cabeçalho são feitas aqui junto com os campos
     * do body para centralizar o feedback de erro em um único lugar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ── Header ────────────────────────────────────────────────────────
            // Idempotency-Key é obrigatório para criação de propostas.
            // O header é lido via $this->header() e injetado no validador
            // através do método withValidator() ou sobrescrevendo prepareForValidation().
            'idempotency_key' => ['required', 'string', 'min:8', 'max:255'],

            // ── Body ──────────────────────────────────────────────────────────
            'cliente_id'  => ['required', 'integer', Rule::exists('clientes', 'id')],
            'produto'     => ['required', 'string', 'max:100'],
            'valor_mensal'=> ['required', 'numeric', 'min:0'],
            'origem'      => ['required', new Enum(PropostaOrigemEnum::class)],
        ];
    }

    /**
     * Injeta o header Idempotency-Key como campo virtual antes da validação,
     * permitindo que as regras acima o valide normalmente.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'O header Idempotency-Key é obrigatório para criação de propostas.',
            'idempotency_key.min'      => 'O header Idempotency-Key deve ter no mínimo 8 caracteres.',
            'cliente_id.exists'        => 'O cliente informado não existe.',
            'origem.enum'              => 'A origem deve ser um dos valores: ' . implode(', ', PropostaOrigemEnum::values()) . '.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente_id'   => 'cliente',
            'produto'      => 'produto',
            'valor_mensal' => 'valor mensal',
            'origem'       => 'origem',
        ];
    }
}
