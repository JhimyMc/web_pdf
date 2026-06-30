<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // Importante para forzar el https

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
        // Esta es la parte que realmente importa para tu PWA:
        // Si detecta que estamos en ngrok, forzamos HTTPS para que todo cargue bien
        if (request()->server->has('HTTP_X_FORWARDED_HOST')) {
            URL::forceScheme('https');
        }
    }
}
