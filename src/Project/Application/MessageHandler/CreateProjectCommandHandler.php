<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Message\Command\CreateProjectCommand;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateProjectCommandHandler
{
    private const DEFAULT_COLUMNS = ['To Do', 'In Progress', 'Done'];

    public function __construct(
        private ProjectRepository $projectRepository,
        private BoardRepository $boardRepository,
        private ColumnRepository $columnRepository,
        private ProjectMemberRepository $projectMemberRepository,
    ) {
    }

    public function __invoke(CreateProjectCommand $command): Project
    {
        $project = Project::create(Uuid::uuid4(), $command->name);
        $this->projectRepository->save($project);

        $board = Board::create(Uuid::uuid4(), $project->id(), $project->name());
        $this->boardRepository->save($board);

        foreach (self::DEFAULT_COLUMNS as $position => $name) {
            $this->columnRepository->save(Column::create(Uuid::uuid4(), $board->id(), $name, $position));
        }

        $this->projectMemberRepository->save(
            ProjectMember::create(
                id: Uuid::uuid4(),
                projectId: $project->id(),
                userId: Uuid::fromString($command->actorId),
                role: MemberRole::Admin,
                isFavourite: true
            ),
        );

        return $project;
    }
}
