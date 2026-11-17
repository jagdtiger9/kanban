<?php

declare(strict_types=1);

namespace App\Mcp\Application\Service;

/**
 * The raw token exists only in this result: it is shown to the user once and never stored.
 */
final readonly class IssuedMcpToken
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
