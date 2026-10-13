<?php

use App\Models\Withdrawal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hard-deletes withdrawals the operator confirmed as spam more than 7 days ago.
Schedule::command('model:prune', ['--model' => Withdrawal::class])->daily();
