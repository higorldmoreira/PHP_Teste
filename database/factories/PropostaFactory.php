<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PropostaOrigemEnum;
use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposta>
 */
class PropostaFactory extends Factory
{
    protected $model = Proposta::class;

    /** Produtos financeiros representativos do domínio. */
    private const PRODUTOS = [
        'Seguro de Vida',
        'Seguro Residencial',
        'Seguro Auto',
        'Previdência Privada',
        'Consórcio Imobiliário',
        'Consórcio de Veículos',
        'Empréstimo Pessoal',
        'Financiamento Imobiliário',
        'Cartão de Crédito Empresarial',
        'Plano de Saúde Empresarial',
    ];

    public function definition(): array
    {
        return [
            // cliente_id pode ser sobrescrito diretamente no create() do Seeder
            'cliente_id'   => Cliente::factory(),

            'produto'      => $this->faker->randomElement(self::PRODUTOS),

            // Valor mensal entre R$ 50,00 e R$ 5.000,00 com 2 casas decimais
            'valor_mensal' => $this->faker->randomFloat(2, 50, 5_000),

            // Status aleatório dentre todos os casos do Enum
            'status'       => $this->faker->randomElement(PropostaStatusEnum::cases())->value,

            'origem'       => $this->faker->randomElement(PropostaOrigemEnum::cases())->value,

            // Versão entre 1 e 3 — propostas raramente chegam à versão 4+
            'versao'       => $this->faker->numberBetween(1, 3),
        ];
    }

    // -------------------------------------------------------------------------
    // Estados (states) — convenientes em testes de integração
    // -------------------------------------------------------------------------

    public function draft(): static
    {
        return $this->state(fn() => ['status' => PropostaStatusEnum::DRAFT->value]);
    }

    public function submitted(): static
    {
        return $this->state(fn() => ['status' => PropostaStatusEnum::SUBMITTED->value]);
    }

    public function approved(): static
    {
        return $this->state(fn() => ['status' => PropostaStatusEnum::APPROVED->value]);
    }

    public function rejected(): static
    {
        return $this->state(fn() => ['status' => PropostaStatusEnum::REJECTED->value]);
    }

    public function canceled(): static
    {
        return $this->state(fn() => [
            'status'     => PropostaStatusEnum::CANCELED->value,
            'deleted_at' => now(), // proposta cancelada costuma ser soft-deleted também
        ]);
    }

    public function porOrigem(PropostaOrigemEnum $origem): static
    {
        return $this->state(fn() => ['origem' => $origem->value]);
    }
}
