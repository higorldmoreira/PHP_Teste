<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditoriaProposta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditoriaProposta
 */
class AuditoriaPropostaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'actor'      => $this->actor,

            // Backed Enum → retorna o value string (ex: 'created', 'status_changed')
            'evento'     => $this->evento->value,

            // Cast 'array' no model já deserializa o JSON automaticamente
            'payload'    => $this->payload,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
