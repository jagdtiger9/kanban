<?php

declare(strict_types=1);

namespace App\Mcp\Domain\ReadModel;

use Ramsey\Uuid\UuidInterface;

/**
 * One card activity entry enriched with the names an agent needs to report it.
 */
final readonly class RecentActivity
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public UuidInterface $projectId,
        public string $projectName,
        public UuidInterface $cardId,
        public string $cardTitle,
        public ?string $actorName,
        public string $type,
        public array $payload,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
