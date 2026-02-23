<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'proposta_id'  => $this->proposta_id,
            'proposta'     => PropostaResource::make($this->whenLoaded('proposta')),
            'status'       => $this->status->value,
            'status_label' => $this->status->label(),
            'valor_total'  => (float) $this->valor_total,
            'observacoes'  => $this->observacoes,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
