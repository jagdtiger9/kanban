<?php

declare(strict_types=1);

namespace App\Project\Application\Service;

use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\UuidInterface;

final class MembershipGuard
{
    public function __construct(
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Effective role of the actor in the project; null when the actor has no
     * access at all. A global admin counts as a project admin everywhere,
     * even without a membership row.
     */
    public function effectiveRole(UuidInterface $projectId, UuidInterface $actorId): ?MemberRole
    {
        if ($this->isGlobalAdmin($actorId)) {
            return MemberRole::Admin;
        }

        return $this->projectMemberRepository->findByProjectAndUser($projectId, $actorId)?->role();
    }

    public function requireMember(UuidInterface $projectId, UuidInterface $actorId): void
    {
        $this->memberOrAdmin($projectId, $actorId);
    }

    /**
     * The actor's actual membership row. A global admin does not bypass this
     * check: per-user state has nowhere to be stored without a row.
     */
    public function requireMembership(UuidInterface $projectId, UuidInterface $actorId): ProjectMember
    {
        $member = $this->projectMemberRepository->findByProjectAndUser($projectId, $actorId);
        if ($member === null) {
            throw new ProjectForbiddenException('Only project members can perform this action');
        }

        return $member;
    }

    public function requireAdmin(UuidInterface $projectId, UuidInterface $actorId): void
    {
        $member = $this->memberOrAdmin($projectId, $actorId);
        if ($member !== null && !$member->isAdmin()) {
            throw new ProjectForbiddenException('Only project admins can perform this action');
        }
    }

    /**
     * @return ProjectMember|null the membership row, or null when the actor
     *                             bypasses membership as a global admin
     */
    private function memberOrAdmin(UuidInterface $projectId, UuidInterface $actorId): ?ProjectMember
    {
        if ($this->isGlobalAdmin($actorId)) {
            return null;
        }
        $member = $this->projectMemberRepository->findByProjectAndUser($projectId, $actorId);
        if ($member === null) {
            throw new ProjectForbiddenException('Only project members can access this project');
        }

        return $member;
    }

    public function isGlobalAdmin(UuidInterface $actorId): bool
    {
        $user = $this->userRepository->findById($actorId);

        return $user !== null && $user->isGlobalAdmin();
    }
}
