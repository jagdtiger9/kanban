<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\ReorderColumnCommand;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\ColumnReorderService;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ReorderColumnCommandHandler
{
    public function __construct(
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private MembershipGuard $guard,
        private ColumnReorderService $reorderService,
    ) {
    }

    public function __invoke(ReorderColumnCommand $command): Column
    {
        $column = $this->columnRepository->findById(Uuid::fromString($command->columnId));
        if ($column === null) {
            throw new ColumnNotFoundException('Column not found');
        }
        $board = $this->boardRepository->findById($column->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every column');
        }
        $actorId = Uuid::fromString($command->actorId);
        $this->guard->requireAdmin($board->projectId(), $actorId);

        $columns = $this->columnRepository->findByBoard($board->id());
        $this->reorderService->reorder($column, $command->targetIndex, $columns, $actorId);
        foreach ($columns as $changed) {
            $this->columnRepository->save($changed);
        }

        return $column;
    }
}
