<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Event listeners are auto-discovered from app/Listeners (Laravel default).
        // Do NOT also register them here — that double-fires the listener.
    }
}
