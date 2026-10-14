<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\FormRenderToken;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;

/** The render token is missing, tampered with or from the future: the form was not loaded from here. */
final class RenderTokenInvalid implements SpamSignal
{
    public function __construct(private readonly FormRenderToken $tokens) {}

    public function key(): string
    {
        return 'render_token_invalid';
    }

    public function fires(SubmissionContext $context): bool
    {
        return $this->tokens->renderedAt($context->renderToken) === null;
    }
}
