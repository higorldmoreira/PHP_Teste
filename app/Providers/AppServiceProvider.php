<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filters\PropostaFilter;
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
        // Services são stateless — bind é semânticamente correto (nova instância por resolve)
        $this->app->bind(PropostaFilter::class);
        $this->app->bind(ClienteService::class);
        $this->app->bind(PropostaService::class);
        $this->app->bind(OrderService::class);
    }

    public function boot(): void
    {
        // ── Rate limiting ─────────────────────────────────────────────────────
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-write', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
