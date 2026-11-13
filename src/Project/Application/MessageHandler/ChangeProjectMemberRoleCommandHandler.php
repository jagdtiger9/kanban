<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\ChangeProjectMemberRoleCommand;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\LastProjectAdminException;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeProjectMemberRoleCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(ChangeProjectMemberRoleCommand $command): ProjectMember
    {
        $projectId = Uuid::fromString($command->projectId);
        $userId = Uuid::fromString($command->userId);
        $actorId = Uuid::fromString($command->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireAdmin($projectId, $actorId);

        $member = $this->projectMemberRepository->findByProjectAndUser($projectId, $userId);
        if ($member === null) {
            throw new ProjectMemberNotFoundException('User is not a member of this project');
        }

        $newRole = MemberRole::from($command->role);
        if ($member->isAdmin() && $newRole !== MemberRole::Admin && $this->isLastAdmin($projectId)) {
            throw new LastProjectAdminException('The last project admin cannot be demoted');
        }

        $member->changeRole($newRole, $actorId);
        $this->projectMemberRepository->save($member);

        return $member;
    }

    private function isLastAdmin(UuidInterface $projectId): bool
    {
        $admins = array_filter(
            $this->projectMemberRepository->findByProject($projectId),
            static fn (ProjectMember $member): bool => $member->isAdmin(),
        );

        return count($admins) === 1;
    }
}
