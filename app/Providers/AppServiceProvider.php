<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Coolify terminates TLS at the proxy. Force generated redirects and
        // absolute URLs to stay on HTTPS once the request reaches Laravel.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
