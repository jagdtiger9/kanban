<?php

declare(strict_types=1);

namespace App\Mcp\Application\Service;

use App\Mcp\Domain\Repository\McpTokenRepository;
use App\Mcp\Domain\ValueObject\McpTokenFormat;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\UuidInterface;

final class McpTokenVerifier
{
    public function __construct(
        private McpTokenRepository $tokenRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return UuidInterface|null the token owner, or null for a malformed, unknown, replaced or expired token
     */
    public function verify(string $rawToken): ?UuidInterface
    {
        $parsed = McpTokenFormat::parse($rawToken);
        if ($parsed === null) {
            return null;
        }
        [$userId, $secret] = $parsed;

        $stored = $this->tokenRepository->findByUser($userId);
        if ($stored === null || !$stored->matches($secret, $this->clock->now())) {
            return null;
        }

        return $userId;
    }
}
