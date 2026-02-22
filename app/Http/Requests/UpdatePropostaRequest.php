<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropostaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Apenas campos livres podem ser atualizados.
     * 'versao' é obrigatório para Optimistic Lock.
     * 'status' é imutável nesta rota (usa os endpoints de transição).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // versao: obrigatório para validar Optimistic Lock no Service
            'versao'       => ['required', 'integer', 'min:1'],

            // sometimes: só valida se o campo estiver presente no payload
            'produto'      => ['sometimes', 'string', 'max:100'],
            'valor_mensal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'versao.required' => 'O campo versao é obrigatório para garantir consistência da atualização (Optimistic Lock).',
            'versao.integer'  => 'O campo versao deve ser um número inteiro.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'versao'       => 'versão',
            'produto'      => 'produto',
            'valor_mensal' => 'valor mensal',
        ];
    }
}
