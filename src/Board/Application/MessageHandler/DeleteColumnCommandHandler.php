<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\DeleteColumnCommand;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotEmptyException;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\ColumnReorderService;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteColumnCommandHandler
{
    public function __construct(
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private CardRepository $cardRepository,
        private MembershipGuard $guard,
        private ColumnReorderService $reorderService,
    ) {
    }

    public function __invoke(DeleteColumnCommand $command): void
    {
        $columnId = Uuid::fromString($command->columnId);

        $column = $this->columnRepository->findById($columnId);
        if ($column === null) {
            throw new ColumnNotFoundException('Column not found');
        }
        $board = $this->boardRepository->findById($column->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every column');
        }
        $this->guard->requireAdmin($board->projectId(), Uuid::fromString($command->actorId));

        if ($this->cardRepository->countByColumn($columnId) > 0) {
            throw new ColumnNotEmptyException('Column must be empty to be deleted');
        }

        $columns = $this->columnRepository->findByBoard($board->id());
        $this->columnRepository->remove($column);

        $remaining = array_values(array_filter(
            $columns,
            static fn (Column $c): bool => !$c->id()->equals($column->id()),
        ));
        $this->reorderService->renumber($remaining);
        foreach ($remaining as $changed) {
            $this->columnRepository->save($changed);
        }
    }
}
