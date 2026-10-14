<?php

declare(strict_types=1);

use App\Http\Controllers\ApplySpamLinkController;
use App\Http\Controllers\SetConsumerLocaleController;
use App\Http\Controllers\ShowImprintController;
use App\Http\Controllers\ShowPrivacyPolicyController;
use App\Http\Controllers\ShowSpamLinkController;
use App\Http\Controllers\ShowWithdrawalFormController;
use App\Http\Controllers\ShowWithdrawalSuccessController;
use App\Http\Controllers\StoreWithdrawalController;
use App\Http\Middleware\SetConsumerLocale;
use App\Http\Middleware\SpamLinkPage;
use Illuminate\Support\Facades\Route;

/**
 * Consumer-facing withdrawal form. SetConsumerLocale applies the cookie-selected
 * language switcher choice to the whole flow (form → submit → success), so the
 * stored locale and the § 356a acknowledgment e-mail follow the consumer's choice.
 */
Route::middleware(SetConsumerLocale::class)->group(function (): void {
    Route::get('/', ShowWithdrawalFormController::class)->name('withdrawal.form');
    Route::post('/', StoreWithdrawalController::class)->name('withdrawal.store');
    Route::get('/success', ShowWithdrawalSuccessController::class)->name('withdrawal.success');

    Route::get('/locale/{locale}', SetConsumerLocaleController::class)
        ->name('locale.set')
        ->where('locale', '[A-Za-z_-]+');

    // Internal legal pages. Each is rendered in the consumer's locale, or a
    // 302-redirect to the operator's external override URL when one is set.
    Route::get('/datenschutz', ShowPrivacyPolicyController::class)->name('legal.privacy');
    Route::get('/impressum', ShowImprintController::class)->name('legal.imprint');
});

/**
 * Signed operator links from the merchant mail and the spam digest. GET shows a
 * confirmation page, only the POST changes state (mail scanners prefetch links). The
 * signature covers path and query only, so it holds behind a TLS-terminating proxy.
 */
Route::middleware(['throttle:30,1', SpamLinkPage::class, 'signed:relative'])
    ->prefix('spam/{withdrawal}/{action}')
    ->whereNumber('withdrawal')
    ->group(function (): void {
        Route::get('/', ShowSpamLinkController::class)->name('spam-link.show');
        Route::post('/', ApplySpamLinkController::class)->name('spam-link.apply');
    });
