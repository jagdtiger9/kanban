<?php

declare(strict_types=1);

namespace App\Mcp\Domain\Repository;

use App\Mcp\Domain\ReadModel\RecentActivity;
use Ramsey\Uuid\UuidInterface;

/**
 * Temporary read model for "what's new": lives in the Mcp module until the query moves
 * to the Board module after MCP testing.
 */
interface RecentActivityReader
{
    /**
     * Card activity created after $since in non-archived projects, newest first.
     *
     * @param UuidInterface|null $memberId restrict to projects of this member; null = all projects
     *
     * @return list<RecentActivity>
     */
    public function findSince(\DateTimeImmutable $since, ?UuidInterface $memberId, int $limit): array;
}
