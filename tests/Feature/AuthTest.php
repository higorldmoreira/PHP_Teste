<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Registro ──────────────────────────────────────────────────────────────

    public function test_usuario_pode_se_registrar_e_recebe_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => ['id', 'name', 'email'],
        ]);
        $response->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com']);
    }

    public function test_registro_falha_com_email_duplicado(): void
    {
        User::factory()->create(['email' => 'duplicado@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Outro Usuário',
            'email'                 => 'duplicado@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_registro_falha_sem_confirmacao_de_senha(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'João',
            'email'    => 'joao2@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ── Login ──────────────────────────────────────────────────────────────────

    public function test_usuario_pode_fazer_login_com_credenciais_validas(): void
    {
        User::factory()->create([
            'email'    => 'teste@example.com',
            'password' => bcrypt('senha-segura'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'teste@example.com',
            'password' => 'senha-segura',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => ['id', 'name', 'email'],
        ]);
    }

    public function test_login_falha_com_credenciais_invalidas(): void
    {
        User::factory()->create(['email' => 'real@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'real@example.com',
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Unauthorized');
    }

    // ── Rotas protegidas ──────────────────────────────────────────────────────

    public function test_acesso_negado_sem_autenticacao(): void
    {
        $this->getJson('/api/v1/propostas')->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    public function test_usuario_autenticado_acessa_rota_protegida(): void
    {
        $this->authAsUser();

        $this->getJson('/api/v1/propostas')->assertStatus(200);
    }

    // ── Me ────────────────────────────────────────────────────────────────────

    public function test_retorna_dados_do_usuario_autenticado(): void
    {
        $user = $this->authAsUser();

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('id', $user->id);
        $response->assertJsonPath('email', $user->email);
    }
}
