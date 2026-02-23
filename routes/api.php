<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PropostaController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — versão 1
|--------------------------------------------------------------------------
| Prefixo base registrado em bootstrap/app.php: /api
| Prefixo adicional aqui: /v1
| URL final: http://app/api/v1/...
*/

Route::prefix('v1')->group(function (): void {

    // ── Autenticação (rotas públicas) ─────────────────────────────────────────

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    // ── Rotas protegidas por autenticação ─────────────────────────────────────

    Route::middleware('auth:api')->group(function (): void {

        // ── Clientes ──────────────────────────────────────────────────────────

        Route::post('clientes', [ClienteController::class, 'store'])
            ->middleware('idempotency')
            ->name('clientes.store');

        Route::get('clientes/{cliente}', [ClienteController::class, 'show'])
            ->name('clientes.show');

        // ── Propostas ─────────────────────────────────────────────────────────

        Route::get('propostas', [PropostaController::class, 'index'])
            ->name('propostas.index');

        Route::post('propostas', [PropostaController::class, 'store'])
            ->middleware('idempotency')
            ->name('propostas.store');

        Route::get('propostas/{proposta}', [PropostaController::class, 'show'])
            ->name('propostas.show');

        Route::patch('propostas/{proposta}', [PropostaController::class, 'update'])
            ->name('propostas.update');

        // Transições de status
        Route::post('propostas/{proposta}/submit', [PropostaController::class, 'submit'])
            ->name('propostas.submit');

        Route::post('propostas/{proposta}/approve', [PropostaController::class, 'approve'])
            ->name('propostas.approve');

        Route::post('propostas/{proposta}/reject', [PropostaController::class, 'reject'])
            ->name('propostas.reject');

        Route::post('propostas/{proposta}/cancel', [PropostaController::class, 'cancel'])
            ->name('propostas.cancel');

        Route::get('propostas/{proposta}/auditoria', [PropostaController::class, 'auditoria'])
            ->name('propostas.auditoria');

        // ── Orders ────────────────────────────────────────────────────────────

        Route::get('orders', [OrderController::class, 'index'])
            ->name('orders.index');

        Route::post('propostas/{proposta}/orders', [OrderController::class, 'store'])
            ->name('orders.store');

        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->name('orders.show');

        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');
    });
});

