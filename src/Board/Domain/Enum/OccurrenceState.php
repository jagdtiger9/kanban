<?php

declare(strict_types=1);

namespace App\Board\Domain\Enum;

enum OccurrenceState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
