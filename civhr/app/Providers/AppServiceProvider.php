<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Keep indexed string columns within the 767-byte key limit of older
        // MySQL/MariaDB shared hosts (utf8mb4 = 4 bytes/char × 191 ≈ 764).
        Schema::defaultStringLength(191);
    }
}
