<?php

declare(strict_types=1);

use App\Delivery\ChannelPolicy;
use App\Enums\SpamLevel;
use App\Events\WithdrawalSubmitted;
use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use App\Models\AcknowledgmentLog;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Http::fake(['ntfy.example.test/*' => Http::response('', 200)]);
    config([
        'revoco.merchant_email' => 'merchant@example.com',
        'revoco.ntfy.enabled' => true,
        'revoco.ntfy.server' => 'https://ntfy.example.test',
        'revoco.ntfy.topic' => 'revoco-topic',
        'revoco.ntfy.token' => null,
    ]);
});

function submitAt(SpamLevel $level): Withdrawal
{
    $row = Withdrawal::factory()->create([
        'spam_level' => $level,
        'spam_reason' => $level === SpamLevel::Green ? null : 'test',
        'email' => 'kunde@example.com',
    ]);

    WithdrawalSubmitted::dispatch($row);

    return $row;
}

it('sends every channel for a green row, with a plain push', function () {
    $row = submitAt(SpamLevel::Green);

    Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($m) => $m->hasTo('kunde@example.com'));
    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->hasTo('merchant@example.com'));
    Mail::assertQueuedCount(2);
    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->headers()).$request->body(), 'warning'));
    expect(AcknowledgmentLog::where('withdrawal_id', $row->id)->count())->toBe(1);
});

it('sends every channel for a yellow row, with the spam marker on the push', function () {
    $row = submitAt(SpamLevel::Yellow);

    Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($m) => $m->hasTo('kunde@example.com'));
    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->hasTo('merchant@example.com'));
    Mail::assertQueuedCount(2);
    Http::assertSent(fn ($request) => str_contains(json_encode($request->headers()).$request->body(), 'warning'));
    expect(AcknowledgmentLog::where('withdrawal_id', $row->id)->count())->toBe(1);
});

it('sends nothing per row for a red row', function () {
    $row = submitAt(SpamLevel::Red);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
    Http::assertNothingSent();
    expect(AcknowledgmentLog::where('withdrawal_id', $row->id)->count())->toBe(0);
});

it('flags a yellow merchant mail as suspected and gives it the mark-as-spam link', function () {
    $row = Withdrawal::factory()->yellow()->create();

    $mail = new WithdrawalNotification($row);

    $mail->assertSeeInHtml('Spam-Verdacht');
    expect($mail->envelope()->subject)->toStartWith('⚠ SPAM-VERDACHT:');
    $mail->assertSeeInHtml('/spam/'.$row->id.'/spam?', escape: false);
});

it('queues the acknowledgment at most once even when the submit event is handled twice', function () {
    $row = Withdrawal::factory()->create(['email' => 'kunde@example.com']);

    WithdrawalSubmitted::dispatch($row);
    WithdrawalSubmitted::dispatch($row);

    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);
});

it('never queues an acknowledgment for a honeypot submit (hotfix 1.0.3 guard)', function () {
    submitCleanWithdrawal([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-1',
        'subject' => 'Schlauchboot',
        'website' => 'http://spam.example',
    ])->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', ['email' => 'kunde@example.com', 'spam_level' => 'red']);
    Mail::assertNotQueued(WithdrawalAcknowledgment::class);
    Mail::assertNotQueued(WithdrawalNotification::class);
    Http::assertNothingSent();
});

it('maps each level to its channels in the policy', function () {
    $green = ChannelPolicy::for(SpamLevel::Green);
    $yellow = ChannelPolicy::for(SpamLevel::Yellow);
    $red = ChannelPolicy::for(SpamLevel::Red);

    expect([$green->acknowledgment, $green->merchantMail, $green->push, $green->suspected])->toBe([true, true, true, false])
        ->and([$yellow->acknowledgment, $yellow->merchantMail, $yellow->push, $yellow->suspected])->toBe([true, true, true, true])
        ->and([$red->acknowledgment, $red->merchantMail, $red->push])->toBe([false, false, false]);
});
