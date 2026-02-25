<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposta;
use Illuminate\Foundation\Events\Dispatchable;

/** Disparado pelo PropostaObserver quando uma proposta é soft-deleted. */
final class PropostaExcluida
{
    use Dispatchable;

    public function __construct(
        public readonly Proposta $proposta,
    ) {}
}
