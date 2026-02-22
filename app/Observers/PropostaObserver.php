<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditoriaEventoEnum;
use App\Models\AuditoriaProposta;
use App\Models\Proposta;

/**
 * PropostaObserver
 *
 * Registra automaticamente entradas de auditoria para cada evento
 * relevante do ciclo de vida de uma Proposta.
 *
 * Registrado via atributo #[ObservedBy(PropostaObserver::class)] no model.
 */
class PropostaObserver
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve o identificador do ator da operação.
     * Usa o usuário autenticado quando disponível; caso contrário, 'system'
     * (ex.: seeders, jobs em background, comandos Artisan).
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id !== null ? 'user:' . $id : 'system';
    }

    // -------------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------------

    /**
     * Disparado após INSERT bem-sucedido.
     *
     * Payload: snapshot completo dos atributos no momento da criação,
     * convertendo Enums para seu valor primitivo (string) para garantir
     * serialização correta no JSON.
     */
    public function created(Proposta $proposta): void
    {
        AuditoriaProposta::registrar(
            proposta: $proposta,
            actor:    $this->actor(),
            evento:   AuditoriaEventoEnum::CREATED,
            payload:  $this->serializeAttributes($proposta->getAttributes()),
        );
    }

    /**
     * Disparado após UPDATE bem-sucedido.
     *
     * Diferencia dois sub-eventos:
     *  - STATUS_CHANGED : quando o campo `status` foi alterado.
     *  - UPDATED_FIELDS : quando qualquer outro campo foi alterado.
     *
     * Payload: apenas os campos que de fato mudaram (getChanges()),
     * usando o novo valor de cada campo.
     */
    public function updated(Proposta $proposta): void
    {
        $changes = $proposta->getChanges();

        // Remove updated_at — não é uma mudança de domínio relevante
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $evento = array_key_exists('status', $changes)
            ? AuditoriaEventoEnum::STATUS_CHANGED
            : AuditoriaEventoEnum::UPDATED_FIELDS;

        AuditoriaProposta::registrar(
            proposta: $proposta,
            actor:    $this->actor(),
            evento:   $evento,
            payload:  $this->serializeAttributes($changes),
        );
    }

    /**
     * Disparado após soft-delete (deleted_at preenchido).
     *
     * Payload: status atual da proposta no momento da exclusão lógica,
     * útil para rastrear em qual estágio a proposta foi removida.
     */
    public function deleted(Proposta $proposta): void
    {
        AuditoriaProposta::registrar(
            proposta: $proposta,
            actor:    $this->actor(),
            evento:   AuditoriaEventoEnum::DELETED_LOGICAL,
            payload:  [
                'status' => $proposta->status instanceof \BackedEnum
                    ? $proposta->status->value
                    : $proposta->status,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Utilitário de serialização
    // -------------------------------------------------------------------------

    /**
     * Converte atributos para tipos primitivos serializáveis em JSON.
     * Backed Enums são convertidos para seu `value` (string/int).
     * Objetos com __toString() são convertidos para string.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function serializeAttributes(array $attributes): array
    {
        return array_map(static function (mixed $value): mixed {
            return match (true) {
                $value instanceof \BackedEnum  => $value->value,
                $value instanceof \Stringable  => (string) $value,
                default                        => $value,
            };
        }, $attributes);
    }
}
