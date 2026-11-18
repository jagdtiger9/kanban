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
     * @return list<array{project: Project, role: MemberRole, memberCount: int, isFavourite: ?bool}>
     */
    public function __invoke(ListProjectsQuery $query): array
    {
        $actorId = Uuid::fromString($query->actorId);

        $user = $this->userRepository->findById($actorId);
        if ($user !== null && $user->isGlobalAdmin()) {
            $projects = $this->projectRepository->findAll();
            $counts = $this->countByProject($projects);
            $favourites = [];
            foreach ($this->projectMemberRepository->findByUser($actorId) as $member) {
                $favourites[$member->projectId()->toString()] = $member->isFavourite();
            }

            return array_map(
                static fn(Project $project): array => [
                    'project' => $project,
                    'role' => MemberRole::Admin,
                    'memberCount' => $counts[$project->id()->toString()] ?? 0,
                    'isFavourite' => $favourites[$project->id()->toString()] ?? null,
                ],
                $projects,
            );
        }

        $projects = $this->projectRepository->findByMember($actorId);
        $counts = $this->countByProject($projects);

        $result = [];
        foreach ($projects as $project) {
            $member = $this->projectMemberRepository->findByProjectAndUser($project->id(), $actorId);
            if ($member === null) {
                throw new \LogicException('Membership must exist for every listed project');
            }

            $result[] = [
                'project' => $project,
                'role' => $member->role(),
                'memberCount' => $counts[$project->id()->toString()] ?? 0,
                'isFavourite' => $member->isFavourite(),
            ];
        }

        return $result;
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<string, int>
     */
    private function countByProject(array $projects): array
    {
        return $this->projectMemberRepository->countByProjects(
            array_map(static fn(Project $project): string => $project->id()->toString(), $projects),
        );
    }
}
