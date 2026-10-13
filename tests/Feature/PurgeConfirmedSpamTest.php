<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function prune(): void
{
    Artisan::call('model:prune', ['--model' => Withdrawal::class]);
}

it('deletes a row confirmed as spam 8 days ago', function () {
    $row = Withdrawal::factory()->confirmedSpam(now()->subDays(8))->create();

    prune();

    expect(Withdrawal::find($row->id))->toBeNull();
});

it('keeps rows that are not confirmed as spam long enough', function () {
    $recent = Withdrawal::factory()->confirmedSpam(now()->subDays(6))->create();
    $unconfirmedRed = Withdrawal::factory()->spam()->create(['created_at' => now()->subDays(30)]);
    $notSpam = Withdrawal::factory()->spam()->notSpam(now()->subDays(30))->create(['created_at' => now()->subDays(30)]);
    $clean = Withdrawal::factory()->create(['created_at' => now()->subDays(30)]);

    prune();

    foreach ([$recent, $unconfirmedRed, $notSpam, $clean] as $row) {
        expect(Withdrawal::find($row->id))->not->toBeNull();
    }
});

it('counts the 7 days from the confirmation', function () {
    $row = Withdrawal::factory()->confirmedSpam(now())->create();

    $this->travel(6)->days();
    prune();
    expect(Withdrawal::find($row->id))->not->toBeNull();

    $this->travel(2)->days();
    prune();
    expect(Withdrawal::find($row->id))->toBeNull();
});

it('cancels the pending purge when the operator corrects to not spam', function () {
    $row = Withdrawal::factory()->confirmedSpam(now())->create();

    $this->travel(3)->days();
    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    $this->travel(8)->days();
    prune();

    expect(Withdrawal::find($row->id))->not->toBeNull();
});

it('schedules the prune daily for the Withdrawal model', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain("0 0 * * *  php artisan model:prune --model='App\\Models\\Withdrawal'")
        ->assertSuccessful();
});
