<?php

declare(strict_types=1);

namespace App\Board\Domain\Enum;

enum ColumnKind: string
{
    case Task = 'task';
    case Sticker = 'sticker';
}
