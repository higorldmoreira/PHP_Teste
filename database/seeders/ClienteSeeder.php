<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClienteSeeder extends Seeder
{
    /**
     * Quantidade de clientes a criar.
     * Sobrescrevível via chamada: $this->call(ClienteSeeder::class, false, ['total' => 100])
     */
    public int $total = 50;

    public function run(): void
    {
        $this->command->info("Criando {$this->total} clientes com propostas...");

        $bar = $this->command->getOutput()->createProgressBar($this->total);
        $bar->start();

        // Envolve tudo em uma única transação para performance e consistência
        DB::transaction(function () use ($bar): void {
            for ($i = 0; $i < $this->total; $i++) {
                // hasPropostas() usa a mágica de relacionamento das factories do Laravel,
                // resolvendo automaticamente a FK cliente_id sem setá-la manualmente.
                Cliente::factory()
                    ->hasPropostas(random_int(1, 5))
                    ->create();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->command->newLine();
        $this->command->info('✔ Clientes e propostas criados com sucesso.');
    }
}
