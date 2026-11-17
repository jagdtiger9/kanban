<?php

declare(strict_types=1);

namespace App\Mcp\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Raw token shape handed to the user: `knb_<userId>_<secret>`. The user id lets the
 * verifier read exactly one stored token instead of scanning all of them.
 */
final class McpTokenFormat
{
    private const string PREFIX = 'knb_';
    private const int UUID_LENGTH = 36;

    public static function compose(UuidInterface $userId, string $secret): string
    {
        return self::PREFIX . $userId->toString() . '_' . $secret;
    }

    /**
     * @return array{UuidInterface, string}|null user id and secret, or null when malformed
     */
    public static function parse(string $token): ?array
    {
        if (!str_starts_with($token, self::PREFIX)) {
            return null;
        }

        $rest = substr($token, strlen(self::PREFIX));
        $userId = substr($rest, 0, self::UUID_LENGTH);
        $separator = substr($rest, self::UUID_LENGTH, 1);
        $secret = substr($rest, self::UUID_LENGTH + 1);

        if ($separator !== '_' || $secret === '' || !Uuid::isValid($userId)) {
            return null;
        }

        return [Uuid::fromString($userId), $secret];
    }
}
