<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cria e autentica um usuário via Passport para os testes.
     * Retorna o usuário autenticado para uso nos asserts.
     */
    protected function authAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Passport::actingAs($user);
        return $user;
    }
}
