<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

use Ramsey\Uuid\UuidInterface;

final readonly class ProjectArchived
{
    public function __construct(
        public UuidInterface $projectId,
        public UuidInterface $actorId,
    ) {
    }
}
