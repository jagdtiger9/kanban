<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Board\Domain\Repository\BoardRepository;
use App\Project\Application\Message\Query\GetProjectQuery;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetProjectQueryHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BoardRepository $boardRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
        private MembershipGuard $guard,
    ) {
    }

    /**
     * @return array{
     *     project: Project,
     *     boardId: UuidInterface,
     *     role: MemberRole,
     *     members: list<array{
     *         userId: UuidInterface,
     *         role: MemberRole,
     *         email: string,
     *         displayName: string,
     *         avatarVersion: ?string,
     *         joinedAt: \DateTimeImmutable
     *     }>
     * }
     */
    public function __invoke(GetProjectQuery $query): array
    {
        $projectId = Uuid::fromString($query->projectId);
        $actorId = Uuid::fromString($query->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireMember($projectId, $actorId);
        $role = $this->guard->effectiveRole($projectId, $actorId);

        $board = $this->boardRepository->findByProject($projectId);
        if ($board === null) {
            throw new \LogicException('Board must exist for every project');
        }

        $members = [];
        foreach ($this->projectMemberRepository->findByProject($projectId) as $member) {
            $user = $this->userRepository->findById($member->userId());
            if ($user === null) {
                throw new \LogicException('User must exist for every member row');
            }

            $members[] = [
                'userId' => $member->userId(),
                'role' => $member->role(),
                'email' => $user->email(),
                'displayName' => $user->displayName(),
                'avatarVersion' => $user->avatarVersion(),
                'joinedAt' => $member->joinedAt(),
            ];
        }

        return [
            'project' => $project,
            'boardId' => $board->id(),
            'role' => $role,
            'members' => $members,
        ];
    }
}
