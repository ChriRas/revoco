<?php

declare(strict_types=1);

use App\Http\Controllers\ShowWithdrawalFormController;
use App\Http\Controllers\ShowWithdrawalSuccessController;
use App\Http\Controllers\StoreWithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowWithdrawalFormController::class)->name('withdrawal.form');
Route::post('/', StoreWithdrawalController::class)->name('withdrawal.store');
Route::get('/success', ShowWithdrawalSuccessController::class)->name('withdrawal.success');
