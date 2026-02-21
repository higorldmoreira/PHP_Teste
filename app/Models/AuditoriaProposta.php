<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditoriaEventoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro imutável de auditoria de uma proposta.
 *
 * Regras de imutabilidade:
 *  - Nunca emitir UPDATE nesta tabela (append-only).
 *  - Nenhum soft delete: registros de auditoria não podem ser ocultados.
 *  - $timestamps = false + CREATED_AT definido manualmente — sem updated_at.
 *
 * @property int                  $id
 * @property int                  $proposta_id
 * @property string               $actor        Ex: "user:42", "system", "api:checkout"
 * @property AuditoriaEventoEnum  $evento
 * @property array<string, mixed> $payload      Snapshot anterior/posterior ou dados do evento
 * @property \Carbon\Carbon       $created_at
 *
 * @property-read Proposta $proposta
 */
class AuditoriaProposta extends Model
{
    /**
     * Tabela append-only — não existe coluna updated_at.
     * Manter $timestamps = true (padrão) para que o Eloquent preencha
     * created_at automaticamente no INSERT.
     * UPDATED_AT = null instrui o Eloquent a nunca tentar setar updated_at,
     * evitando erro de coluna inexistente sem desligar todo o sistema de timestamps.
     */
    public const UPDATED_AT = null;

    protected $table = 'auditoria_propostas';

    // -------------------------------------------------------------------------
    // Mass Assignment
    // -------------------------------------------------------------------------

    /** @var list<string> */
    protected $fillable = [
        'proposta_id',
        'actor',
        'evento',
        'payload',
    ];

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    protected function casts(): array
    {
        return [
            // Backed Enum — $auditoria->evento retorna AuditoriaEventoEnum
            'evento'     => AuditoriaEventoEnum::class,
            // JSON deserializado automaticamente para array associativo PHP
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    /**
     * Proposta à qual este registro pertence.
     * withTrashed() garante acesso mesmo após soft-delete da proposta.
     *
     * @return BelongsTo<Proposta, AuditoriaProposta>
     */
    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class)->withTrashed();
    }

    // -------------------------------------------------------------------------
    // Factory helper (uso nos Services)
    // -------------------------------------------------------------------------

    /**
     * Cria e persiste um registro de auditoria de forma expressiva.
     *
     * Exemplo:
     *   AuditoriaProposta::registrar(
     *       proposta: $proposta,
     *       actor:    "user:{$userId}",
     *       evento:   AuditoriaEventoEnum::STATUS_CHANGED,
     *       payload:  ['de' => $anterior->value, 'para' => $novo->value],
     *   );
     *
     * @param  array<string, mixed>  $payload
     */
    public static function registrar(
        Proposta $proposta,
        string $actor,
        AuditoriaEventoEnum $evento,
        array $payload = [],
    ): static {
        return static::create([
            'proposta_id' => $proposta->id,
            'actor'       => $actor,
            'evento'      => $evento->value,
            'payload'     => $payload,
        ]);
    }
}
