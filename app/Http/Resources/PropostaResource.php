<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Proposta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Proposta
 */
class PropostaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'cliente_id'   => $this->cliente_id,

            // Relacionamento condicional: incluso apenas se o model foi
            // carregado via ->with('cliente') ou ->load('cliente')
            'cliente'      => new ClienteResource($this->whenLoaded('cliente')),

            'produto'      => $this->produto,

            // Converte string decimal para float para serialização JSON correta
            'valor_mensal' => (float) $this->valor_mensal,

            // Backed Enum → retorna o value string (ex: 'draft', 'approved')
            'status'       => $this->status->value,
            'origem'       => $this->origem->value,

            'versao'       => $this->versao,

            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
