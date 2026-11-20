<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Exception;

final class InvalidUserEventCursorException extends \RuntimeException
{
    public static function malformed(): self
    {
        return new self('Malformed cursor');
    }
}
