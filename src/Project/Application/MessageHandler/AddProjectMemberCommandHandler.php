<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\AddProjectMemberCommand;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectMemberAlreadyExistsException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddProjectMemberCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(AddProjectMemberCommand $command): ProjectMember
    {
        $projectId = Uuid::fromString($command->projectId);
        $userId = Uuid::fromString($command->userId);
        $actorId = Uuid::fromString($command->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireAdmin($projectId, $actorId);

        if ($this->userRepository->findById($userId) === null) {
            throw new UserNotFoundException('User not found');
        }
        if ($this->projectMemberRepository->findByProjectAndUser($projectId, $userId) !== null) {
            throw new ProjectMemberAlreadyExistsException('User is already a member of this project');
        }

        $member = ProjectMember::create(
            Uuid::uuid4(),
            $projectId,
            $userId,
            MemberRole::from($command->role),
        );
        $this->projectMemberRepository->save($member);

        return $member;
    }
}
