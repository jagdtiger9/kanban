<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

/**
 * Personal Centrifugo channel of a user in the "call" namespace.
 */
final class CallChannel
{
    public static function forUser(string $userId): string
    {
        return 'call:' . $userId;
    }
}
