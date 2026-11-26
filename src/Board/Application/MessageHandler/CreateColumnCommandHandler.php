<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CreateColumnCommand;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateColumnCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BoardRepository $boardRepository,
        private ColumnRepository $columnRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(CreateColumnCommand $command): Column
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireAdmin($projectId, $actorId);

        $board = $this->boardRepository->findByProject($projectId);
        if ($board === null) {
            throw new \LogicException('Board must exist for every project');
        }

        $column = Column::create(
            Uuid::uuid4(),
            $board->id(),
            $command->name,
            count($this->columnRepository->findByBoard($board->id())),
            ColumnKind::from($command->kind),
            $actorId,
        );
        $this->columnRepository->save($column);

        return $column;
    }
}
