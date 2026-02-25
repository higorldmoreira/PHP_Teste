<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposta;
use Illuminate\Foundation\Events\Dispatchable;

/** Disparado pelo PropostaObserver após a criação de uma proposta. */
final class PropostaCriada
{
    use Dispatchable;

    public function __construct(
        public readonly Proposta $proposta,
    ) {}
}
