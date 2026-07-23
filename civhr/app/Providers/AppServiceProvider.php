<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Vite::prefetch(concurrency: 3);

        // Keep indexed string columns within the 767-byte key limit of older
        // MySQL/MariaDB shared hosts (utf8mb4 = 4 bytes/char × 191 ≈ 764).
        Schema::defaultStringLength(191);

        // Once the site runs on https, every generated URL must be https too
        // (mixed-content assets would otherwise be blocked by the browser).
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // New passwords must beat "password123": length + letters + numbers.
        Password::defaults(fn () => Password::min(10)->letters()->numbers());
    }
}
