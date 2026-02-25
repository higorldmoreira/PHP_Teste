<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditoriaEventoEnum;
use App\Events\PropostaCamposAlterados;
use App\Events\PropostaCriada;
use App\Events\PropostaExcluida;
use App\Events\PropostaStatusAlterado;
use App\Models\AuditoriaProposta;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener assíncrono que persiste os registros de auditoria.
 *
 * Implementa ShouldQueue para desacoplar a auditoria do ciclo HTTP —
 * a resposta ao cliente não espera pela gravação no banco.
 *
 * Em testes, use Event::fake() ou Queue::fake() para isolar este listener.
 */
class RegistrarAuditoriaListener implements ShouldQueue
{
    use InteractsWithQueue;

    /** Nome da fila onde os jobs de auditoria são processados. */
    public string $queue = 'auditoria';

    /** Número de tentativas em caso de falha. */
    public int $tries = 3;

    /** Backoff em segundos entre tentativas. */
    public int $backoff = 5;

    public function handlePropostaCriada(PropostaCriada $event): void
    {
        AuditoriaProposta::registrar(
            proposta: $event->proposta,
            actor:    'system',
            evento:   AuditoriaEventoEnum::CREATED,
            payload:  $this->serialize($event->proposta->getAttributes()),
        );
    }

    public function handlePropostaStatusAlterado(PropostaStatusAlterado $event): void
    {
        AuditoriaProposta::registrar(
            proposta: $event->proposta,
            actor:    'system',
            evento:   AuditoriaEventoEnum::STATUS_CHANGED,
            payload:  [
                'status_anterior' => $event->statusAnterior->value,
                'status_novo'     => $event->statusNovo->value,
            ],
        );
    }

    public function handlePropostaCamposAlterados(PropostaCamposAlterados $event): void
    {
        AuditoriaProposta::registrar(
            proposta: $event->proposta,
            actor:    'system',
            evento:   AuditoriaEventoEnum::UPDATED_FIELDS,
            payload:  $this->serialize($event->camposAlterados),
        );
    }

    public function handlePropostaExcluida(PropostaExcluida $event): void
    {
        AuditoriaProposta::registrar(
            proposta: $event->proposta,
            actor:    'system',
            evento:   AuditoriaEventoEnum::DELETED_LOGICAL,
            payload:  [
                'status' => $event->proposta->status instanceof \BackedEnum
                    ? $event->proposta->status->value
                    : $event->proposta->status,
            ],
        );
    }

    /** Serializa atributos do modelo para o payload de auditoria. */
    private function serialize(array $attributes): array
    {
        return array_map(static function (mixed $value): mixed {
            return match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \Stringable => (string) $value,
                default                       => $value,
            };
        }, $attributes);
    }
}
