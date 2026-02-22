<?php

use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrado como alias para ser aplicado apenas nas rotas de store (POST)
        $middleware->alias([
            'idempotency' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(
            fn(BusinessException $e, $request) => $e->render($request)
        );
        $exceptions->renderable(
            fn(ConcurrencyException $e, $request) => $e->render($request)
        );
    })->create();
