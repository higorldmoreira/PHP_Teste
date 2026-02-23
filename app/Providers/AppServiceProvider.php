<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ClienteService;
use App\Services\OrderService;
use App\Services\PropostaService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClienteService::class);
        $this->app->singleton(PropostaService::class);
        $this->app->singleton(OrderService::class);
    }

    public function boot(): void
    {
        // Rate limiting: 60 req/min para leitura, 20 req/min para escrita
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
