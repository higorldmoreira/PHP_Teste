<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Proposta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria pedidos de demonstração a partir das propostas APPROVED existentes.
 * Distribui os pedidos entre PENDING, APPROVED e CANCELED para cobrir
 * os cenários principais da API.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Criando pedidos de demonstração...');

        DB::transaction(function (): void {
            $propostas = Proposta::where('status', 'approved')
                ->doesntHave('orders')
                ->get();

            if ($propostas->isEmpty()) {
                $this->command->warn('Nenhuma proposta APPROVED disponível. Execute PropostaSeeder primeiro.');
                return;
            }

            foreach ($propostas as $index => $proposta) {
                $status = match ($index % 3) {
                    0       => OrderStatus::PENDING,
                    1       => OrderStatus::APPROVED,
                    default => OrderStatus::CANCELED,
                };

                Order::create([
                    'proposta_id' => $proposta->id,
                    'status'      => $status->value,
                    'valor_total' => $proposta->valor_mensal,
                    'observacoes' => "Pedido de demonstração #{$index}",
                ]);
            }
        });

        $total = Order::count();
        $this->command->info("✔ {$total} pedidos disponíveis.");

        foreach (OrderStatus::cases() as $status) {
            $count = Order::where('status', $status->value)->count();
            $this->command->line("  {$status->label()}: {$count}");
        }
    }
}
