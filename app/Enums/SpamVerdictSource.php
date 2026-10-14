<?php

declare(strict_types=1);

namespace App\Enums;

/** Who gave a withdrawal's spam verdict: the system's auto-sorting rule or the operator. */
enum SpamVerdictSource: string
{
    case Auto = 'auto';
    case Operator = 'operator';
}
