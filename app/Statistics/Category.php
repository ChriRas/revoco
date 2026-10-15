<?php

declare(strict_types=1);

namespace App\Statistics;

/** The reporting category of a counted withdrawal; see `Outcome::category()`. */
enum Category: string
{
    case Genuine = 'genuine';
    case Suspected = 'suspected';
    case Spam = 'spam';
}
