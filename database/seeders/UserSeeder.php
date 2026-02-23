<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
            ],
        );

        $this->command->info('✔ Usuário padrão criado: admin@teste.com / password');
    }
}
