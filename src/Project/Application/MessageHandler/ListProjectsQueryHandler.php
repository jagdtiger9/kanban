<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Query\ListProjectsQuery;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListProjectsQueryHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return list<array{project: Project, role: MemberRole}>
     */
    public function __invoke(ListProjectsQuery $query): array
    {
        $actorId = Uuid::fromString($query->actorId);

        $user = $this->userRepository->findById($actorId);
        if ($user !== null && $user->isGlobalAdmin()) {
            return array_map(
                static fn (Project $project): array => ['project' => $project, 'role' => MemberRole::Admin],
                $this->projectRepository->findAll(),
            );
        }

        $result = [];
        foreach ($this->projectRepository->findByMember($actorId) as $project) {
            $member = $this->projectMemberRepository->findByProjectAndUser($project->id(), $actorId);
            if ($member === null) {
                throw new \LogicException('Membership must exist for every listed project');
            }

            $result[] = ['project' => $project, 'role' => $member->role()];
        }

        return $result;
    }
}
