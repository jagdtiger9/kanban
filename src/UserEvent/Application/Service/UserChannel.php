<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Service;

/**
 * Personal Centrifugo channel of a user in the "user" namespace.
 */
final class UserChannel
{
    public static function forUser(string $userId): string
    {
        return 'user:' . $userId;
    }
}
