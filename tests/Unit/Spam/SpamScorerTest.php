<?php

declare(strict_types=1);

use App\Enums\SpamLevel;
use App\Spam\SpamAssessment;
use App\Spam\SpamScorer;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Illuminate\Support\Facades\Exceptions;

require_once __DIR__.'/Support.php';

/** A signal that fires or not on demand. */
function fixedSignal(string $key, bool $fires): SpamSignal
{
    return new readonly class($key, $fires) implements SpamSignal
    {
        public function __construct(private string $key, private bool $fires) {}

        public function key(): string
        {
            return $this->key;
        }

        public function fires(SubmissionContext $context): bool
        {
            return $this->fires;
        }
    };
}

it('sums the configured weights of the signals that fired, heaviest first', function () {
    config(['revoco.spam.weights' => ['a' => 10, 'b' => 40, 'c' => 25, 'd' => 99]]);

    $assessment = (new SpamScorer([
        fixedSignal('a', true), fixedSignal('b', true), fixedSignal('c', true), fixedSignal('d', false),
    ]))->assess(spamContext());

    expect($assessment)->toBeInstanceOf(SpamAssessment::class)
        ->and($assessment->score)->toBe(75)
        ->and($assessment->signals)->toBe(['b', 'c', 'a'])
        ->and($assessment->reason())->toBe('b,c,a');
});

it('weighs an unknown key 0 and stores no reason when nothing fired', function () {
    config(['revoco.spam.weights' => []]);

    $none = (new SpamScorer([fixedSignal('unknown', false)]))->assess(spamContext());
    $unknown = (new SpamScorer([fixedSignal('unknown', true)]))->assess(spamContext());

    expect($none->score)->toBe(0)
        ->and($none->level)->toBe(SpamLevel::Green)
        ->and($none->reason())->toBeNull()
        ->and($unknown->score)->toBe(0)
        ->and($unknown->level)->toBe(SpamLevel::Green)
        ->and($unknown->reason())->toBe('unknown');
});

it('maps the score to a level at the threshold edges', function (int $score, SpamLevel $level) {
    config([
        'revoco.spam.thresholds' => ['yellow' => 30, 'red' => 70],
        'revoco.spam.weights' => ['x' => $score],
    ]);

    expect((new SpamScorer([fixedSignal('x', true)]))->assess(spamContext())->level)->toBe($level);
})->with([
    'yellow - 1' => [29, SpamLevel::Green],
    'yellow' => [30, SpamLevel::Yellow],
    'red - 1' => [69, SpamLevel::Yellow],
    'red' => [70, SpamLevel::Red],
    'far above red' => [345, SpamLevel::Red],
]);

it('reads thresholds from config', function () {
    config(['revoco.spam.thresholds' => ['yellow' => 5, 'red' => 8], 'revoco.spam.weights' => ['x' => 6]]);

    expect((new SpamScorer([fixedSignal('x', true)]))->assess(spamContext())->level)->toBe(SpamLevel::Yellow);
});

it('ships defaults where red needs the honeypot or two strong signals', function () {
    $weights = config('revoco.spam.weights');
    $red = config('revoco.spam.thresholds.red');

    expect($weights['honeypot'])->toBeGreaterThanOrEqual($red)
        ->and($weights['content_gibberish'] + $weights['fill_time_too_fast'])->toBeGreaterThanOrEqual($red)
        ->and($weights['content_gibberish'] + $weights['user_agent_ancient'])->toBeGreaterThanOrEqual($red)
        ->and($weights['user_agent_ancient'] + $weights['fill_time_too_fast'])->toBeLessThan($red)
        ->and($weights['throttle'])->toBeGreaterThanOrEqual(config('revoco.spam.thresholds.yellow'))->toBeLessThan($red);
    foreach (array_diff_key($weights, ['honeypot' => 1]) as $key => $weight) {
        expect($weight)->toBeLessThan($red, $key);
    }
});

it('registers exactly one signal per configured weight in the spam.signals tag', function () {
    $keys = collect(app()->tagged('spam.signals'))->map(fn (SpamSignal $signal): string => $signal->key())->sort()->values()->all();
    $weighted = array_keys(config('revoco.spam.weights'));
    sort($weighted);

    expect($keys)->toBe($weighted);
});

it('resolves the container scorer and scores a bot-like context red', function () {
    $assessment = app(SpamScorer::class)->assess(spamContext([
        'honeypotFilled' => true, 'renderToken' => null, 'userAgent' => null, 'name' => 'xKqWnPzRtY',
    ]));

    expect($assessment->signals)->toContain('honeypot', 'render_token_invalid', 'user_agent_ancient', 'content_gibberish', 'name_without_space')
        ->and($assessment->level)->toBe(SpamLevel::Red);
});

it('auto-confirms the honeypot together with any of the configured bot signals', function (string $second) {
    $assessment = (new SpamScorer([fixedSignal('honeypot', true), fixedSignal($second, true)]))->assess(spamContext());

    expect($assessment->autoConfirmed)->toBeTrue();
})->with(['user_agent_ancient', 'fill_time_too_fast', 'render_token_invalid', 'content_gibberish']);

it('does not auto-confirm the honeypot alone, with a network signal, or a bot signal without it', function (array $keys) {
    $signals = array_map(fn (string $key): SpamSignal => fixedSignal($key, true), $keys);

    expect((new SpamScorer($signals))->assess(spamContext())->autoConfirmed)->toBeFalse();
})->with([
    'honeypot alone' => [['honeypot']],
    'honeypot + network' => [['honeypot', 'drop_network', 'asn_drop', 'tor_exit']],
    'bot signals without honeypot' => [['user_agent_ancient', 'fill_time_too_fast', 'render_token_invalid', 'content_gibberish']],
]);

it('reports a throwing signal and scores the others as if it had not fired', function () {
    Exceptions::fake();
    config(['revoco.spam.weights' => ['broken' => 50, 'b' => 40]]);

    $broken = new readonly class implements SpamSignal
    {
        public function key(): string
        {
            return 'broken';
        }

        public function fires(SubmissionContext $context): bool
        {
            throw new RuntimeException('cache store unavailable');
        }
    };

    $assessment = (new SpamScorer([$broken, fixedSignal('b', true)]))->assess(spamContext());

    expect($assessment->score)->toBe(40)
        ->and($assessment->signals)->toBe(['b']);
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'cache store unavailable');
});
