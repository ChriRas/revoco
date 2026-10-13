<?php

declare(strict_types=1);

namespace App\Enums;

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Models\Withdrawal;

/** What a signed mail link does when its confirmation page is submitted. */
enum SpamLinkAction: string
{
    case Spam = 'spam';
    case NotSpam = 'not-spam';

    /** Applies the operator verdict through the same Action the panel uses. */
    public function applyTo(Withdrawal $withdrawal): void
    {
        match ($this) {
            self::Spam => app(MarkWithdrawalAsSpam::class)->handle($withdrawal),
            self::NotSpam => app(MarkWithdrawalAsNotSpam::class)->handle($withdrawal),
        };
    }
}
