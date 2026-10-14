<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** The operator's verdict on a withdrawal; it overrides the system's level. */
enum SpamVerdict: string implements HasColor, HasLabel
{
    case Spam = 'spam';
    case NotSpam = 'not_spam';

    public function getLabel(): string
    {
        return __('panel.spam_verdict.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Spam => 'danger',
            self::NotSpam => 'success',
        };
    }
}
