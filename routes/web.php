<?php

declare(strict_types=1);

use App\Http\Controllers\SetConsumerLocaleController;
use App\Http\Controllers\ShowPrivacyPolicyController;
use App\Http\Controllers\ShowWithdrawalFormController;
use App\Http\Controllers\ShowWithdrawalSuccessController;
use App\Http\Controllers\StoreWithdrawalController;
use App\Http\Middleware\SetConsumerLocale;
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

    // Internal legal page (privacy). Rendered in the consumer's locale, or a
    // 302-redirect to the operator's external override URL when one is set.
    Route::get('/datenschutz', ShowPrivacyPolicyController::class)->name('legal.privacy');
});
