<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;

/**
 * What became of a withdrawal after the system's level: no verdict yet, spam (decided by the
 * auto-sorting rule or by the operator), or corrected to "not spam".
 *
 * Together with the level this is the stored counter state. The reporting figures (category,
 * auto-sorted, false alarm, missed) are pure functions of (level, outcome) and stay here, so
 * a later reclassification never needs the purged rows.
 */
enum Outcome: string
{
    case None = 'none';
    case SpamAuto = 'spam_auto';
    case SpamOperator = 'spam_operator';
    case NotSpam = 'not_spam';

    /** The outcome for a row's verdict and verdict source; a spam verdict without a source counts as the operator's. */
    public static function fromVerdict(?SpamVerdict $verdict, ?SpamVerdictSource $source): self
    {
        return match ($verdict) {
            null => self::None,
            SpamVerdict::NotSpam => self::NotSpam,
            SpamVerdict::Spam => $source === SpamVerdictSource::Auto ? self::SpamAuto : self::SpamOperator,
        };
    }

    /** Genuine, suspected or spam: every state falls into exactly one category. */
    public function category(SpamLevel $level): Category
    {
        return match ($this) {
            self::NotSpam => Category::Genuine,
            self::SpamAuto, self::SpamOperator => Category::Spam,
            self::None => match ($level) {
                SpamLevel::Green => Category::Genuine,
                SpamLevel::Yellow => Category::Suspected,
                SpamLevel::Red => Category::Spam,
            },
        };
    }

    /** Currently sorted out by the auto-sorting rule. */
    public function isAutoSorted(): bool
    {
        return $this === self::SpamAuto;
    }

    /** Classified red, but the operator corrected it to "not spam". */
    public function isFalseAlarm(SpamLevel $level): bool
    {
        return $level === SpamLevel::Red && $this === self::NotSpam;
    }

    /** Classified green or yellow, but the operator marked it as spam. */
    public function isMissed(SpamLevel $level): bool
    {
        return $level !== SpamLevel::Red && $this === self::SpamOperator;
    }
}
