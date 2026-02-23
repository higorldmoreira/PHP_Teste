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
                // 1 proposta em cada estado principal por cliente de amostra
                Proposta::factory()->draft()->create(['cliente_id' => $cliente->id]);
                Proposta::factory()->submitted()->create(['cliente_id' => $cliente->id]);
                Proposta::factory()->approved()->create(['cliente_id' => $cliente->id]);
                Proposta::factory()->rejected()->create(['cliente_id' => $cliente->id]);
                Proposta::factory()->canceled()->create(['cliente_id' => $cliente->id]);
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
