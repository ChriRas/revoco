<?php

declare(strict_types=1);

use App\Spam\FormRenderToken;
use App\Spam\Signals\ContentGibberish;
use App\Spam\Signals\FieldLengthsEqual;
use App\Spam\Signals\FillTimeTooFast;
use App\Spam\Signals\Honeypot;
use App\Spam\Signals\NameWithoutSpace;
use App\Spam\Signals\RenderTokenInvalid;
use App\Spam\Signals\Throttle;
use App\Spam\Signals\UserAgentAncient;
use Illuminate\Support\Facades\RateLimiter;

require_once __DIR__.'/Support.php';

it('exposes the stable snake_case keys', function () {
    expect((new Honeypot)->key())->toBe('honeypot')
        ->and((new Throttle)->key())->toBe('throttle')
        ->and((new RenderTokenInvalid(new FormRenderToken))->key())->toBe('render_token_invalid')
        ->and((new FillTimeTooFast(new FormRenderToken))->key())->toBe('fill_time_too_fast')
        ->and((new UserAgentAncient)->key())->toBe('user_agent_ancient')
        ->and((new ContentGibberish)->key())->toBe('content_gibberish')
        ->and((new NameWithoutSpace)->key())->toBe('name_without_space')
        ->and((new FieldLengthsEqual)->key())->toBe('field_lengths_equal');
});

// honeypot

it('fires the honeypot only when the decoy field was filled', function () {
    expect((new Honeypot)->fires(spamContext(['honeypotFilled' => true])))->toBeTrue()
        ->and((new Honeypot)->fires(spamContext(['honeypotFilled' => false])))->toBeFalse();
});

// throttle

it('fires the throttle from the sixth submit of one IP within the window', function () {
    $signal = new Throttle;

    foreach (range(1, 5) as $i) {
        expect($signal->fires(spamContext()))->toBeFalse();
    }

    expect($signal->fires(spamContext()))->toBeTrue()
        // Another IP has its own window.
        ->and($signal->fires(spamContext(['ip' => '203.0.113.8'])))->toBeFalse();
});

it('counts the throttle window per hashed IP and never stores the raw IP', function () {
    (new Throttle)->fires(spamContext(['ip' => '203.0.113.7']));

    expect(RateLimiter::attempts('withdrawal-submit:'.sha1('203.0.113.7')))->toBe(1);
});

// render token

it('fires render_token_invalid for a missing, tampered or future token only', function () {
    $tokens = new FormRenderToken;
    $signal = new RenderTokenInvalid($tokens);

    expect($signal->fires(spamContext(['renderToken' => null])))->toBeTrue()
        ->and($signal->fires(spamContext(['renderToken' => 'garbage'])))->toBeTrue()
        ->and($signal->fires(spamContext(['renderToken' => $tokens->issue()])))->toBeFalse();
});

it('fires fill_time_too_fast below the minimum fill time and not at it', function () {
    $this->travelTo(now()->startOfSecond());
    $tokens = new FormRenderToken;
    $signal = new FillTimeTooFast($tokens);
    $token = $tokens->issue();
    $min = config('revoco.spam.min_fill_seconds');

    expect($signal->fires(spamContext(['renderToken' => $token, 'submittedAt' => now()->toImmutable()->addSeconds($min - 1)])))->toBeTrue()
        ->and($signal->fires(spamContext(['renderToken' => $token, 'submittedAt' => now()->toImmutable()->addSeconds($min)])))->toBeFalse()
        ->and($signal->fires(spamContext(['renderToken' => $token, 'submittedAt' => now()->toImmutable()->addHours(5)])))->toBeFalse();
});

it('does not fire fill_time_too_fast without a valid token (that is render_token_invalid)', function () {
    expect((new FillTimeTooFast(new FormRenderToken))->fires(spamContext(['renderToken' => null])))->toBeFalse();
});

// user agent

it('fires user_agent_ancient for missing, IE and below-floor browsers', function (?string $ua) {
    expect((new UserAgentAncient)->fires(spamContext(['userAgent' => $ua])))->toBeTrue();
})->with([
    'missing' => [null],
    'empty' => ['  '],
    'MSIE' => ['Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)'],
    'IE11' => ['Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko'],
    'Chrome 99' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.0.0 Safari/537.36'],
    'Chromium Edge 90' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36 Edg/90.0.818.51'],
    'Firefox 91' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0) Gecko/20100101 Firefox/91.0'],
    'Safari 13' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.2 Safari/605.1.15'],
]);

it('does not fire user_agent_ancient for current or unrecognised browsers', function (string $ua) {
    expect((new UserAgentAncient)->fires(spamContext(['userAgent' => $ua])))->toBeFalse();
})->with([
    'Chrome 156' => [CLEAN_BROWSER_USER_AGENT],
    'Chrome 100 (floor)' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36'],
    'Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0'],
    'Firefox 158' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:158.0) Gecko/20100101 Firefox/158.0'],
    'Safari 26' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15'],
    'Safari 14 (floor)' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Safari/605.1.15'],
    'iOS Chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1'],
    'iOS Firefox' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/141.0 Mobile/15E148 Safari/605.1.15'],
    'unknown family' => ['SomeCustomClient/1.0'],
    'Symfony test default' => ['Symfony'],
]);

it('reads the user agent floors from config', function () {
    config(['revoco.spam.user_agent_floors.chrome' => 200]);

    expect((new UserAgentAncient)->fires(spamContext(['userAgent' => CLEAN_BROWSER_USER_AGENT])))->toBeTrue();
});

// content

it('fires content_gibberish for random-looking name or subject and for a digit in the name', function (array $fields) {
    expect((new ContentGibberish)->fires(spamContext($fields)))->toBeTrue();
})->with([
    'camel noise in name' => [['name' => 'xKqWnPzRtY']],
    'camel noise in subject' => [['subject' => 'Ich widerrufe qWeRtZuIoP sofort']],
    'digit in name' => [['name' => 'Max Mustermann 2']],
]);

it('does not fire content_gibberish for ordinary German text', function (array $fields) {
    expect((new ContentGibberish)->fires(spamContext($fields)))->toBeFalse();
})->with([
    'persona' => [['name' => 'Max Mustermann']],
    'compound Angstschweiß' => [['subject' => 'Angstschweiß-Waschmaschine']],
    'compound Waschmaschine' => [['subject' => 'Waschmaschine Modell Superwaschvollautomat']],
    'short camel case' => [['name' => 'McDonald']],
    'digit in subject is fine' => [['subject' => 'Bestellung 4711 widerrufen']],
]);

it('keeps the iPhoneProMax false positive known: three transitions fire', function () {
    expect((new ContentGibberish)->fires(spamContext(['subject' => 'iPhoneProMax'])))->toBeTrue();
});

it('fires name_without_space for a single-word name only', function () {
    expect((new NameWithoutSpace)->fires(spamContext(['name' => 'Mustermann'])))->toBeTrue()
        ->and((new NameWithoutSpace)->fires(spamContext(['name' => '  Mustermann  '])))->toBeTrue()
        ->and((new NameWithoutSpace)->fires(spamContext(['name' => 'Max Mustermann'])))->toBeFalse();
});

it('fires field_lengths_equal when the non-empty fields share a length of 6 or more', function () {
    $signal = new FieldLengthsEqual;

    expect($signal->fires(spamContext(['name' => 'aaaaaaaa', 'orderNumber' => 'bbbbbbbb', 'subject' => 'cccccccc'])))->toBeTrue()
        // Two non-empty fields are enough when the order number is omitted.
        ->and($signal->fires(spamContext(['name' => 'aaaaaaaa', 'orderNumber' => null, 'subject' => 'cccccccc'])))->toBeTrue()
        // One differing length breaks it.
        ->and($signal->fires(spamContext(['name' => 'aaaaaaaa', 'orderNumber' => 'bbbbbbbb', 'subject' => 'ccc'])))->toBeFalse()
        // Too short.
        ->and($signal->fires(spamContext(['name' => 'abc', 'orderNumber' => 'def', 'subject' => 'ghi'])))->toBeFalse()
        // A single non-empty field cannot be "equal".
        ->and($signal->fires(spamContext(['name' => 'aaaaaaaa', 'orderNumber' => null, 'subject' => ''])))->toBeFalse()
        ->and($signal->fires(spamContext()))->toBeFalse();
});
