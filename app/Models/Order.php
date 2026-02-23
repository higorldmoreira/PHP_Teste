<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'proposta_id',
        'status',
        'valor_total',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status'      => OrderStatus::class,
            'valor_total' => 'decimal:2',
        ];
    }


    public function proposta(): BelongsTo
    {
        return $this->belongsTo(Proposta::class);
    }

    public function scopePorStatus($query, OrderStatus $status): void
    {
        $query->where('status', $status->value);
    }
}
