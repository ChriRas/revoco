<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/**
 * Name or subject looks random: a long word with several lower-to-upper case
 * transitions ("xKqWnPzRtY"), or a digit in the name.
 *
 * Deliberately narrow: a consonant-run or vowel-ratio rule would flag German
 * compounds such as "Angstschweiß". A camel-case brand ("iPhoneProMax") is a known
 * false positive and stays below red on its own.
 */
final class ContentGibberish implements SpamSignal
{
    private const int MIN_TOKEN_LENGTH = 8;

    private const int MIN_CASE_TRANSITIONS = 3;

    public function key(): string
    {
        return 'content_gibberish';
    }

    public function fires(SubmissionContext $context): bool
    {
        if (preg_match('/\d/', $context->name) === 1) {
            return true;
        }

        return $this->hasRandomToken($context->name) || $this->hasRandomToken($context->subject);
    }

    private function hasRandomToken(string $text): bool
    {
        foreach (preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (mb_strlen($token) < self::MIN_TOKEN_LENGTH) {
                continue;
            }

            if (preg_match_all('/\p{Ll}(?=\p{Lu})/u', $token) >= self::MIN_CASE_TRANSITIONS) {
                return true;
            }
        }

        return false;
    }
}
