<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property string      $nome
 * @property string      $email
 * @property string      $documento  CPF (11 dígitos) ou CNPJ (14 dígitos) sem formatação
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Proposta> $propostas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Proposta> $propostasAtivas
 */
class Cliente extends Model
{
    /** @use HasFactory<\Database\Factories\ClienteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'nome',
        'email',
        'documento',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Todas as propostas do cliente, incluindo soft-deleted.
     *
     * @return HasMany<Proposta>
     */
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class);
    }

    /**
     * Apenas propostas não deletadas (default scope do SoftDeletes).
     *
     * @return HasMany<Proposta>
     */
    public function propostasAtivas(): HasMany
    {
        return $this->hasMany(Proposta::class)->withoutTrashed();
    }

    /**
     * Filtra clientes pelo documento (CPF ou CNPJ).
     *
     * Uso: Cliente::porDocumento('12345678901')->first()
     *
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopePorDocumento(Builder $query, string $documento): Builder
    {
        return $query->where('documento', $documento);
    }
}
