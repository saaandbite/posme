<?php

namespace App\Providers;

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
        // Produksi: jangan pernah bocorkan stack trace / detail error ke
        // pengunjung, apa pun isi .env di server. Di lokal (APP_ENV=local)
        // debug tetap mengikuti APP_DEBUG seperti biasa.
        if ($this->app->environment('production')) {
            config(['app.debug' => false]);
        }
    }
}
