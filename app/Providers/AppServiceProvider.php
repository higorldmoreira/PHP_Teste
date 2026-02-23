<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ClienteService;
use App\Services\OrderService;
use App\Services\PropostaService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClienteService::class);
        $this->app->singleton(PropostaService::class);
        $this->app->singleton(OrderService::class);
    }
}
