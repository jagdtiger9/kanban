<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Adapter;

use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use App\UserEvent\Application\Dto\CardEventContext;
use App\UserEvent\Application\Port\UserEventContextProvider;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserEventContextProvider::class)]
final class UserEventContextProviderAdapter implements UserEventContextProvider
{
    public function __construct(
        private CardRepository $cards,
        private ColumnRepository $columns,
        private BoardRepository $boards,
        private ProjectRepository $projects,
        private UserRepository $users,
    ) {
    }

    public function card(UuidInterface $cardId): ?CardEventContext
    {
        $card = $this->cards->findById($cardId);
        if ($card === null) {
            return null;
        }

        $column = $this->columns->findById($card->columnId());
        if ($column === null) {
            return null;
        }

        $board = $this->boards->findById($column->boardId());
        if ($board === null) {
            return null;
        }

        $project = $this->projects->findById($board->projectId());
        if ($project === null) {
            return null;
        }

        return new CardEventContext($project->id(), $project->name(), $card->title());
    }

    public function projectName(UuidInterface $projectId): ?string
    {
        return $this->projects->findById($projectId)?->name();
    }

    public function userName(UuidInterface $userId): ?string
    {
        return $this->users->findById($userId)?->displayName();
    }
}
