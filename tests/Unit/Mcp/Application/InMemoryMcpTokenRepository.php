<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Application;

use App\Mcp\Domain\Repository\McpTokenRepository;
use App\Mcp\Domain\ValueObject\McpToken;
use Ramsey\Uuid\UuidInterface;

final class InMemoryMcpTokenRepository implements McpTokenRepository
{
    /** @var array<string, McpToken> */
    public array $tokens = [];

    public function save(McpToken $token): void
    {
        $this->tokens[$token->userId->toString()] = $token;
    }

    public function findByUser(UuidInterface $userId): ?McpToken
    {
        return $this->tokens[$userId->toString()] ?? null;
    }
}
