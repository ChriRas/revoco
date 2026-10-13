<?php

declare(strict_types=1);

namespace App\Enums;

/** What queued a withdrawal's acknowledgment. */
enum AcknowledgmentTrigger: string
{
    case Submit = 'submit';
    case NotSpam = 'not_spam';
}
