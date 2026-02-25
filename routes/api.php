<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IdempotencyKeyController;
use App\Http\Controllers\Api\V1\ClienteController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PropostaController;
use Illuminate\Support\Facades\Route;

// ── Utilitários (sem versionamento, sem rate limit) ───────────────────────
Route::get('/health', HealthController::class)->name('health');
Route::get('/idempotency-key', IdempotencyKeyController::class)->name('idempotency-key.generate');

// ── API v1 ─────────────────────────────────────────────────────────────────
Route::prefix('v1')->middleware(['throttle:api', 'api.version'])->group(function (): void {

    Route::post('clientes',          [ClienteController::class, 'store'])->middleware(['idempotency', 'throttle:api-write'])->name('clientes.store');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');

    Route::get('propostas',                      [PropostaController::class, 'index'])->name('propostas.index');
    Route::post('propostas',                     [PropostaController::class, 'store'])->middleware(['idempotency', 'throttle:api-write'])->name('propostas.store');
    Route::get('propostas/{proposta}',           [PropostaController::class, 'show'])->name('propostas.show');
    Route::patch('propostas/{proposta}',         [PropostaController::class, 'update'])->middleware('throttle:api-write')->name('propostas.update');
    Route::post('propostas/{proposta}/submit',   [PropostaController::class, 'submit'])->middleware('throttle:api-write')->name('propostas.submit');
    Route::post('propostas/{proposta}/approve',  [PropostaController::class, 'approve'])->middleware('throttle:api-write')->name('propostas.approve');
    Route::post('propostas/{proposta}/reject',   [PropostaController::class, 'reject'])->middleware('throttle:api-write')->name('propostas.reject');
    Route::post('propostas/{proposta}/cancel',   [PropostaController::class, 'cancel'])->middleware('throttle:api-write')->name('propostas.cancel');
    Route::get('propostas/{proposta}/auditoria', [PropostaController::class, 'auditoria'])->name('propostas.auditoria');

    Route::get('orders',                       [OrderController::class, 'index'])->name('orders.index');
    Route::post('propostas/{proposta}/orders', [OrderController::class, 'store'])->middleware('throttle:api-write')->name('orders.store');
    Route::get('orders/{order}',               [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/cancel',       [OrderController::class, 'cancel'])->middleware('throttle:api-write')->name('orders.cancel');
});
