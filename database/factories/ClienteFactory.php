<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        // 70% CPF, 30% CNPJ — distribuição realista para o domínio de propostas
        $documento = $this->faker->boolean(70)
            ? $this->gerarCpf()
            : $this->gerarCnpj();

        return [
            'nome'      => $this->faker->name(),
            // unique() garante colisão zero entre registros da mesma execução
            'email'     => $this->faker->unique()->safeEmail(),
            'documento' => $documento,
        ];
    }

    // -------------------------------------------------------------------------
    // Estados (states) — usados nos testes
    // -------------------------------------------------------------------------

    /** Cliente com CPF explícito. */
    public function comCpf(): static
    {
        return $this->state(fn() => ['documento' => $this->gerarCpf()]);
    }

    /** Cliente com CNPJ explícito. */
    public function comCnpj(): static
    {
        return $this->state(fn() => ['documento' => $this->gerarCnpj()]);
    }

    // -------------------------------------------------------------------------
    // Geração de documentos válidos
    // Implementados localmente para não depender do locale pt_BR do Faker,
    // garantindo que os dígitos verificadores sejam sempre matematicamente corretos.
    // -------------------------------------------------------------------------

    /**
     * Gera um CPF válido com 11 dígitos (sem formatação).
     */
    private function gerarCpf(): string
    {
        // 9 dígitos base aleatórios
        $n = array_map(static fn() => random_int(0, 9), range(0, 8));

        // Primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $n[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $n[9]  = $resto < 2 ? 0 : 11 - $resto;

        // Segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $n[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $n[10] = $resto < 2 ? 0 : 11 - $resto;

        return implode('', $n);
    }

    /**
     * Gera um CNPJ válido com 14 dígitos (sem formatação).
     * Os 4 últimos dígitos do bloco são fixos como "0001" (matriz).
     */
    private function gerarCnpj(): string
    {
        // 8 dígitos do CNPJ base + sufixo de filial "0001"
        $n = array_map(static fn() => random_int(0, 9), range(0, 7));
        array_push($n, 0, 0, 0, 1); // índices 8–11

        // Primeiro dígito verificador
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma   = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += $n[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $n[12] = $resto < 2 ? 0 : 11 - $resto;

        // Segundo dígito verificador
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma   = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += $n[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $n[13] = $resto < 2 ? 0 : 11 - $resto;

        return implode('', $n);
    }
}
