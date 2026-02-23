<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Proposta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proposta_id' => Proposta::factory(),
            'status'      => OrderStatus::PENDING->value,
            'valor_total' => fake()->randomFloat(2, 100, 5000),
            'observacoes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::PENDING->value]);
    }

    public function approved(): static
    {
        return $this->state(['status' => OrderStatus::APPROVED->value]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => OrderStatus::REJECTED->value]);
    }

    public function shipped(): static
    {
        return $this->state(['status' => OrderStatus::SHIPPED->value]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => OrderStatus::DELIVERED->value]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => OrderStatus::CANCELED->value]);
    }
}
