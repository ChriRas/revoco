<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\FormRenderToken;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Illuminate\Support\Facades\Config;

/**
 * Submitted sooner after the render than a human can fill the form.
 *
 * An invalid token never fires here (that is `render_token_invalid`). There is no
 * "too slow" counterpart: a consumer may leave the tab open for hours.
 */
final class FillTimeTooFast implements SpamSignal
{
    public function __construct(private readonly FormRenderToken $tokens) {}

    public function key(): string
    {
        return 'fill_time_too_fast';
    }

    public function fires(SubmissionContext $context): bool
    {
        $renderedAt = $this->tokens->renderedAt($context->renderToken);

        if ($renderedAt === null) {
            return false;
        }

        return $renderedAt->diffInSeconds($context->submittedAt, absolute: true) < Config::integer('revoco.spam.min_fill_seconds');
    }
}
