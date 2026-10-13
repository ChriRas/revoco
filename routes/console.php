<?php

use App\Models\AbusedAddress;
use App\Models\SpamNetworkHit;
use App\Models\Withdrawal;
use App\Spam\SpamDigestSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hard-deletes withdrawals the operator confirmed as spam more than 7 days ago.
Schedule::command('model:prune', ['--model' => Withdrawal::class])->daily();

// Refreshes the country / ASN databases and the listed-network feeds; hits older than 90 days no longer count.
Schedule::command('revoco:network-data:refresh')->dailyAt('04:15')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => SpamNetworkHit::class])->daily();

// Reclaims abused-address entries more than a year past their last confirmation (lookups ignore them already).
Schedule::command('model:prune', ['--model' => AbusedAddress::class])->daily();

// Morning digest of unresolved red rows, at the time set on the notifications page.
SpamDigestSchedule::register(app(Illuminate\Console\Scheduling\Schedule::class));
