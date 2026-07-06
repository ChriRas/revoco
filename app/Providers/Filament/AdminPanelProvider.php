<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Resources\WithdrawalResource;
use App\Http\Middleware\SetBackendLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(null)
            ->passwordReset(null)
            // Explicit Revoco branding — set here, not derived from APP_NAME, so the
            // panel (login, header, page <title>, favicon) is correct on every
            // deployment even when the operator does not set APP_NAME. The neutral-
            // by-default rule governs the CONSUMER form, not Revoco's own admin tool.
            ->brandName('Revoco')
            ->brandLogo(asset('img/revoco-logo.svg'))
            ->darkModeBrandLogo(asset('img/revoco-logo-dark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('img/revoco-favicon.svg'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->homeUrl(fn (): string => WithdrawalResource::getUrl('index'))
            ->renderHook(
                PanelsRenderHook::TOPBAR_AFTER,
                fn (): string => view('panels.legal-content-warning')->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([SetBackendLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
