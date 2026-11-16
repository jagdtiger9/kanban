<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\RenameColumnCommand;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RenameColumnCommandHandler
{
    public function __construct(
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(RenameColumnCommand $command): Column
    {
        $column = $this->column($command->columnId);
        $board = $this->boardRepository->findById($column->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every column');
        }
        $actorId = Uuid::fromString($command->actorId);
        $this->guard->requireAdmin($board->projectId(), $actorId);

        $column->rename($command->name, $actorId);
        $this->columnRepository->save($column);

        return $column;
    }

    private function column(string $columnId): Column
    {
        $column = $this->columnRepository->findById(Uuid::fromString($columnId));
        if ($column === null) {
            throw new ColumnNotFoundException('Column not found');
        }

        return $column;
    }
}
