<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

use App\Project\Domain\Enum\MemberRole;
use Ramsey\Uuid\UuidInterface;

final readonly class ProjectMemberRoleChanged
{
    public function __construct(
        public UuidInterface $projectId,
        public UuidInterface $userId,
        public MemberRole $newRole,
        public UuidInterface $actorId,
    ) {
    }
}
