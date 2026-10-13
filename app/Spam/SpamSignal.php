<?php

declare(strict_types=1);

namespace App\Spam;

/**
 * One spam signal: a snake_case key and a yes/no judgement of a submit.
 *
 * A signal only classifies — it never blocks. Its weight lives in
 * `config('revoco.spam.weights.<key>')`; its label in `panel.spam_signal.<key>`.
 * New signals are appended to the `spam.signals` container tag.
 */
interface SpamSignal
{
    /** The stable snake_case key stored in `spam_reason`. */
    public function key(): string;

    public function fires(SubmissionContext $context): bool;
}
