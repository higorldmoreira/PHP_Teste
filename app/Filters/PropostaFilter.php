<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\PropostaStatusEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsula a lógica de filtragem e ordenação de Propostas,
 * removendo essa responsabilidade de PropostaService (SRP).
 */
class PropostaFilter
{
    /** Campos permitidos para ordenação — allowlist contra SQL injection via query string. */
    private const ALLOWED_SORTS = ['created_at', 'updated_at', 'valor_mensal', 'status', 'versao'];

    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        $query = $this->applyStatus($query, $filters);
        $query = $this->applyClienteId($query, $filters);
        $query = $this->applySort($query, $filters);

        return $query;
    }

    private function applyStatus(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? null;

        // Ignora valores fora do enum — evita retorno vazio silencioso por typo
        if ($status === null || ! in_array($status, PropostaStatusEnum::values(), strict: true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    private function applyClienteId(Builder $query, array $filters): Builder
    {
        return $query->when(
            $filters['cliente_id'] ?? null,
            fn(Builder $q, mixed $id) => $q->where('cliente_id', (int) $id),
        );
    }

    private function applySort(Builder $query, array $filters): Builder
    {
        $field     = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($field, self::ALLOWED_SORTS, strict: true)) {
            $field = 'created_at';
        }

        return $query->orderBy($field, $direction);
    }
}
