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
     * Tabela append-only: sem coluna updated_at.
     * UPDATED_AT = null instrui o Eloquent a nunca tentar setar essa coluna,
     * mantendo created_at automático via INSERT sem desligar $timestamps.
     */
    public const UPDATED_AT = null;

    protected $table = 'auditoria_propostas';

    /** @var list<string> */
    protected $fillable = [
        'proposta_id',
        'actor',
        'evento',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'evento'     => AuditoriaEventoEnum::class,
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * withTrashed() garante que a auditoria continue acessível
     * mesmo depois de a proposta ser soft-deletada.
     *
     * @return BelongsTo<Proposta, AuditoriaProposta>
     */
    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class)->withTrashed();
    }

    /**
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
