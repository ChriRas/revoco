<?php

declare(strict_types=1);

namespace App\Providers;

use App\Network\MmdbNetworkLookup;
use App\Network\NetworkData;
use App\Network\NetworkLookup;
use App\Spam\Signals;
use App\Spam\SpamScorer;
use App\Spam\SpamSignal;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** Spam signals are a tagged list: further slices append their class here without touching the scorer. */
        $this->app->tag([
            Signals\Honeypot::class,
            Signals\Throttle::class,
            Signals\RenderTokenInvalid::class,
            Signals\FillTimeTooFast::class,
            Signals\UserAgentAncient::class,
            Signals\ContentGibberish::class,
            Signals\NameWithoutSpace::class,
            Signals\FieldLengthsEqual::class,
            Signals\DropNetwork::class,
            Signals\AsnDrop::class,
            Signals\LearnedNetwork::class,
            Signals\TorExit::class,
            Signals\CountryOutsideShipping::class,
            Signals\AbusedAddress::class,
        ], 'spam.signals');

        $this->app->singleton(NetworkData::class);
        $this->app->singleton(NetworkLookup::class, MmdbNetworkLookup::class);

        $this->app->bind(SpamScorer::class, function (Application $app): SpamScorer {
            /** @var iterable<SpamSignal> $signals */
            $signals = $app->tagged('spam.signals');

            return new SpamScorer($signals);
        });
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
