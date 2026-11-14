<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Project\Domain\Enum\MemberRole;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class ProjectMemberRoleChanged implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $projectId,
        public string $userId,
        public MemberRole $newRole,
        string $actorId,
    ) {
        $this->initEvent($projectId, $actorId);
    }
}
