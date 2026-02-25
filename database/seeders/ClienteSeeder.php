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
        $existing = Cliente::count();

        if ($existing >= $this->total) {
            $this->command->info("Seeder idêmpotente: {$existing} clientes já existem, nada a criar.");
            return;
        }

        $toCreate = $this->total - $existing;
        $this->command->info("Criando {$toCreate} clientes com propostas...");

        $bar = $this->command->getOutput()->createProgressBar($toCreate);
        $bar->start();

        DB::transaction(function () use ($toCreate, $bar): void {
            for ($i = 0; $i < $toCreate; $i++) {
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
