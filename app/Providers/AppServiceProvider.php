<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Proposta;
use App\Services\OrderService;
use App\Services\PropostaService;
use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PropostaService::class, fn () => new PropostaService());
        $this->app->singleton(OrderService::class, fn () => new OrderService(new Order()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(CarbonInterval::days(15));
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::personalAccessTokensExpireIn(CarbonInterval::months(6));
    }
}
