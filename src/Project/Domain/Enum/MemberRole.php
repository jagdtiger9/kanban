<?php

declare(strict_types=1);

namespace App\Project\Domain\Enum;

enum MemberRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
