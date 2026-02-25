<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\PropostaStatusEnum;
use App\Models\Proposta;
use Illuminate\Foundation\Events\Dispatchable;

/** Disparado pelo PropostaObserver quando o campo `status` de uma proposta muda. */
final class PropostaStatusAlterado
{
    use Dispatchable;

    public function __construct(
        public readonly Proposta          $proposta,
        public readonly PropostaStatusEnum $statusAnterior,
        public readonly PropostaStatusEnum $statusNovo,
    ) {}
}
