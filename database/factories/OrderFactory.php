<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Proposta;
use App\Models\User;
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
            'user_id'     => User::factory(),
            'status'      => OrderStatus::Pending->value,
            'valor_total' => fake()->randomFloat(2, 100, 5000),
            'observacoes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending->value]);
    }

    public function approved(): static
    {
        return $this->state(['status' => OrderStatus::Approved->value]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled->value]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => OrderStatus::Delivered->value]);
    }
}
