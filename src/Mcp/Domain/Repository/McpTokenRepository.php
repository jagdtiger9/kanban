<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Repository;

use App\Mcp\Domain\ValueObject\McpToken;
use Ramsey\Uuid\UuidInterface;

interface McpTokenRepository
{
    /** Stores the token, replacing the user's previous one. */
    public function save(McpToken $token): void;

    public function findByUser(UuidInterface $userId): ?McpToken;
}
