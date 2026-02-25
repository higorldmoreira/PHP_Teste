<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PropostaStatusEnum;
use App\Models\Cliente;
use App\Models\Proposta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria um conjunto representativo de propostas em diferentes estados
 * para demonstrar a máquina de estados e facilitar testes manuais.
 */
class PropostaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Criando propostas de demonstração...');

        DB::transaction(function (): void {
            $clientes = Cliente::all();

            if ($clientes->isEmpty()) {
                $this->command->warn('Nenhum cliente encontrado. Execute ClienteSeeder primeiro.');
                return;
            }

            foreach ($clientes->take(10) as $cliente) {
                // Idempotente: cria apenas os estados que ainda não existem para o cliente
                $existingStatuses = $cliente->propostas()->pluck('status')->toArray();

                foreach (['draft', 'submitted', 'approved', 'rejected', 'canceled'] as $state) {
                    if (! in_array($state, $existingStatuses, strict: true)) {
                        Proposta::factory()->{$state}()->create(['cliente_id' => $cliente->id]);
                    }
                }
            }
        });

        $total = Proposta::count();
        $this->command->info("✔ {$total} propostas disponíveis.");

        foreach (PropostaStatusEnum::cases() as $status) {
            $count = Proposta::where('status', $status->value)->count();
            $this->command->line("  {$status->label()}: {$count}");
        }
    }
}
