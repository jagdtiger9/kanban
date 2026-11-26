<?php

declare(strict_types=1);

namespace App\Board\Domain\Enum;

enum RecurrenceKind: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
