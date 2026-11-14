<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use Ramsey\Uuid\Uuid;

final class BoardChannelResolver
{
    public function __construct(
        private BoardRepository $boardRepository,
        private ColumnRepository $columnRepository,
        private CardRepository $cardRepository,
    ) {
    }

    public function forProject(string $projectId): string
    {
        return 'kanban:' . $projectId;
    }

    public function forBoard(string $boardId): ?string
    {
        $board = $this->boardRepository->findById(Uuid::fromString($boardId));

        return $board === null ? null : $this->forProject($board->projectId()->toString());
    }

    public function forCard(string $cardId): ?string
    {
        $card = $this->cardRepository->findById(Uuid::fromString($cardId));

        return $card === null ? null : $this->forColumn($card->columnId()->toString());
    }

    private function forColumn(string $columnId): ?string
    {
        $column = $this->columnRepository->findById(Uuid::fromString($columnId));
        if ($column === null) {
            return null;
        }

        $board = $this->boardRepository->findById($column->boardId());

        return $board === null ? null : $this->forProject($board->projectId()->toString());
    }
}
