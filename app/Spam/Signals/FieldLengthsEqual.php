<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** At least two of name / order number / subject are filled with strings of one identical length of 6 or more. */
final class FieldLengthsEqual implements SpamSignal
{
    private const int MIN_LENGTH = 6;

    public function key(): string
    {
        return 'field_lengths_equal';
    }

    public function fires(SubmissionContext $context): bool
    {
        $lengths = [];

        foreach ([$context->name, $context->orderNumber, $context->subject] as $value) {
            $length = mb_strlen(trim((string) $value));

            if ($length > 0) {
                $lengths[] = $length;
            }
        }

        return count($lengths) >= 2
            && count(array_unique($lengths)) === 1
            && $lengths[0] >= self::MIN_LENGTH;
    }
}
