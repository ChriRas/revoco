<?php

declare(strict_types=1);

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Statistics\Category;
use App\Statistics\Outcome;

it('derives the outcome from the verdict and its source', function () {
    expect(Outcome::fromVerdict(null, null))->toBe(Outcome::None)
        ->and(Outcome::fromVerdict(SpamVerdict::Spam, SpamVerdictSource::Auto))->toBe(Outcome::SpamAuto)
        ->and(Outcome::fromVerdict(SpamVerdict::Spam, SpamVerdictSource::Operator))->toBe(Outcome::SpamOperator)
        ->and(Outcome::fromVerdict(SpamVerdict::Spam, null))->toBe(Outcome::SpamOperator)
        ->and(Outcome::fromVerdict(SpamVerdict::NotSpam, SpamVerdictSource::Operator))->toBe(Outcome::NotSpam);
});

/**
 * (level, outcome) => [level, outcome, category, auto-sorted, false alarm, missed]
 *
 * @return array<string, array{string, string, Category, bool, bool, bool}>
 */
function stateTable(): array
{
    return [
        'green none' => ['green', 'none', Category::Genuine, false, false, false],
        'green spam_auto' => ['green', 'spam_auto', Category::Spam, true, false, false],
        'green spam_operator' => ['green', 'spam_operator', Category::Spam, false, false, true],
        'green not_spam' => ['green', 'not_spam', Category::Genuine, false, false, false],
        'yellow none' => ['yellow', 'none', Category::Suspected, false, false, false],
        'yellow spam_auto' => ['yellow', 'spam_auto', Category::Spam, true, false, false],
        'yellow spam_operator' => ['yellow', 'spam_operator', Category::Spam, false, false, true],
        'yellow not_spam' => ['yellow', 'not_spam', Category::Genuine, false, false, false],
        'red none' => ['red', 'none', Category::Spam, false, false, false],
        'red spam_auto' => ['red', 'spam_auto', Category::Spam, true, false, false],
        'red spam_operator' => ['red', 'spam_operator', Category::Spam, false, false, false],
        'red not_spam' => ['red', 'not_spam', Category::Genuine, false, true, false],
    ];
}

dataset('states', fn (): array => stateTable());

it('maps every (level, outcome) state to its category and quality flags', function (
    string $level,
    string $outcome,
    Category $category,
    bool $autoSorted,
    bool $falseAlarm,
    bool $missed,
) {
    $level = SpamLevel::from($level);
    $outcome = Outcome::from($outcome);

    expect($outcome->category($level))->toBe($category)
        ->and($outcome->isAutoSorted())->toBe($autoSorted)
        ->and($outcome->isFalseAlarm($level))->toBe($falseAlarm)
        ->and($outcome->isMissed($level))->toBe($missed);
})->with('states');

it('has a table row for every combination of level and outcome', function () {
    $expected = [];

    foreach (SpamLevel::cases() as $level) {
        foreach (Outcome::cases() as $outcome) {
            $expected[] = $level->value.' '.$outcome->value;
        }
    }

    expect(array_keys(stateTable()))->toEqualCanonicalizing($expected);
});
