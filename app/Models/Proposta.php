<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropostaOrigemEnum;
use App\Enums\PropostaStatusEnum;
use App\Observers\PropostaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order>            $orders
 */
#[ObservedBy(PropostaObserver::class)]
class Proposta extends Model
{
    /** @use HasFactory<\Database\Factories\PropostaFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'produto',
        'valor_mensal',
        'status',
        'origem',
        'versao',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PropostaStatusEnum::class,
            'origem'       => PropostaOrigemEnum::class,
            // 'decimal:2' retorna string — use bcmath para operações monetárias
            'valor_mensal' => 'decimal:2',
            'versao'       => 'integer',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

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

    /**
     * Pedidos gerados a partir desta proposta.
     *
     * @return HasMany<Order>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

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

}
