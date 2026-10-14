<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The name, trimmed, is a single word (first and last name were expected). */
final class NameWithoutSpace implements SpamSignal
{
    public function key(): string
    {
        return 'name_without_space';
    }

    public function fires(SubmissionContext $context): bool
    {
        $name = trim($context->name);

        return $name !== '' && ! str_contains($name, ' ');
    }
}
