<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\PropostaCamposAlterados;
use App\Events\PropostaCriada;
use App\Events\PropostaExcluida;
use App\Events\PropostaStatusAlterado;
use App\Enums\PropostaStatusEnum;
use App\Models\Proposta;

/**
 * PropostaObserver
 *
 * Atua como dispatcher de eventos do domínio.
 * A lógica de auditoria foi movida para RegistrarAuditoriaListener (ShouldQueue),
 * desacoplando a gravação do ciclo de vida HTTP.
 */
class PropostaObserver
{
    public function created(Proposta $proposta): void
    {
        PropostaCriada::dispatch($proposta);
    }

    public function updated(Proposta $proposta): void
    {
        $changes = $proposta->getChanges();
        // updated_at e versao são ruído — versao muda a cada save e não é evento de negócio
        unset($changes['updated_at'], $changes['versao']);

        if (empty($changes)) {
            return;
        }

        if (array_key_exists('status', $changes)) {
            // getRawOriginal() garante a string bruta do banco, nunca o Enum já convertido
            $statusAnterior = PropostaStatusEnum::from(
                (string) $proposta->getRawOriginal('status')
            );

            PropostaStatusAlterado::dispatch(
                $proposta,
                $statusAnterior,
                $proposta->status,
            );
        } else {
            PropostaCamposAlterados::dispatch($proposta, $changes);
        }
    }

    public function deleted(Proposta $proposta): void
    {
        PropostaExcluida::dispatch($proposta);
    }
}
