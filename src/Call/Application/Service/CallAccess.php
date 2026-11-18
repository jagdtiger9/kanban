<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\UuidInterface;

/**
 * Calls follow board access: only project members (and global admins) may
 * see or join a project's room.
 */
final class CallAccess
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function requireMember(UuidInterface $projectId, UuidInterface $actorId): Project
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireMember($projectId, $actorId);

        return $project;
    }
}
