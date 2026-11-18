<?php

declare(strict_types=1);

namespace App\User\Domain\ValueObject;

enum UserRole: string
{
    case ROLE_USER = 'ROLE_USER';
    case ROLE_GLOBAL_ADMIN = 'ROLE_GLOBAL_ADMIN';
    case ROLE_NEWS_MAKER = 'ROLE_NEWS_MAKER';
}
