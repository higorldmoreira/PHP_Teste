<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposta;
use Illuminate\Foundation\Events\Dispatchable;

/** Disparado pelo PropostaObserver quando campos de conteúdo (não-status) são alterados. */
final class PropostaCamposAlterados
{
    use Dispatchable;

    /**
     * @param array<string, mixed> $camposAlterados  Diff das colunas modificadas.
     */
    public function __construct(
        public readonly Proposta $proposta,
        public readonly array    $camposAlterados,
    ) {}
}
