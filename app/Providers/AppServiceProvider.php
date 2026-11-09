<?php

namespace App\Providers;

use App\Services\OpenRouterClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OpenRouterClient::class, fn (): OpenRouterClient => OpenRouterClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('chat', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
