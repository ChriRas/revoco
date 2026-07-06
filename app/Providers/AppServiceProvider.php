<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
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

        // Sticky rich-editor toolbar (slice-024). Registered here — not on the panel
        // provider — so it does not collide with the branding changes in slice-022.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('panels.sticky-editor-toolbar')->render(),
        );
    }
}
