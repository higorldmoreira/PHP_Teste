<?php

namespace App\Providers;

use App\Services\OrderService;
use App\Services\PropostaService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PropostaService::class);
        $this->app->singleton(OrderService::class);
    }

    public function boot(): void {}
}
