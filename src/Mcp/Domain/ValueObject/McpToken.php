<?php

declare(strict_types=1);

namespace App\Mcp\Domain\ValueObject;

use Ramsey\Uuid\UuidInterface;

/**
 * The stored side of a user's MCP access token: only the SHA-256 hash of the secret is
 * kept, together with its expiry. One token per user — issuing a new one replaces it.
 */
final readonly class McpToken
{
    public function __construct(
        public UuidInterface $userId,
        public string $secretHash,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function matches(string $secret, \DateTimeImmutable $now): bool
    {
        return $now < $this->expiresAt && hash_equals($this->secretHash, self::hashSecret($secret));
    }
}
