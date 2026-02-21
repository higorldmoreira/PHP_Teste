<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropostaOrigemEnum;
use App\Enums\PropostaStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                 $id
 * @property int                 $cliente_id
 * @property string              $produto
 * @property string              $valor_mensal   Decimal retornado como string pelo MySQL
 * @property PropostaStatusEnum  $status
 * @property PropostaOrigemEnum  $origem
 * @property int                 $versao
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read Cliente                                                        $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AuditoriaProposta> $auditorias
 */
class Proposta extends Model
{
    /** @use HasFactory<\Database\Factories\PropostaFactory> */
    use HasFactory;
    use SoftDeletes;

    // -------------------------------------------------------------------------
    // Mass Assignment
    // -------------------------------------------------------------------------

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'produto',
        'valor_mensal',
        'status',
        'origem',
        'versao',
    ];

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    protected function casts(): array
    {
        return [
            // Backed Enums — $proposta->status retorna PropostaStatusEnum
            'status'       => PropostaStatusEnum::class,
            // Backed Enum — $proposta->origem retorna PropostaOrigemEnum
            'origem'       => PropostaOrigemEnum::class,
            // 'decimal:2' retorna string — use bcmath para operações monetárias
            'valor_mensal' => 'decimal:2',
            'versao'       => 'integer',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------------------------

    /**
     * Cliente dono desta proposta.
     *
     * @return BelongsTo<Cliente, Proposta>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Histórico de auditoria desta proposta.
     * Registros imutáveis — nunca deletar.
     *
     * @return HasMany<AuditoriaProposta>
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(AuditoriaProposta::class)->orderBy('created_at');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra propostas por status.
     *
     * Uso: Proposta::porStatus(PropostaStatusEnum::APPROVED)->get()
     *
     * @param  Builder<Proposta>  $query
     * @return Builder<Proposta>
     */
    public function scopePorStatus(Builder $query, PropostaStatusEnum $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Filtra propostas por origem.
     *
     * @param  Builder<Proposta>  $query
     * @return Builder<Proposta>
     */
    public function scopePorOrigem(Builder $query, PropostaOrigemEnum $origem): Builder
    {
        return $query->where('origem', $origem->value);
    }

    // -------------------------------------------------------------------------
    // Helpers de domínio
    // -------------------------------------------------------------------------

    /**
     * Incrementa a versão da proposta.
     * Deve ser chamado dentro de uma transação pelo PropostaService.
     */
    public function incrementarVersao(): static
    {
        $this->increment('versao');

        return $this;
    }
}
