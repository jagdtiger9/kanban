<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Adapter;

use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\User\Domain\Entity\User;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardParticipantsProvider::class)]
final class CardParticipantsProviderAdapter implements CardParticipantsProvider
{
    public function __construct(
        private CardRepository $cards,
        private ColumnRepository $columns,
        private BoardRepository $boards,
    ) {
    }

    public function assigneeIds(UuidInterface $cardId): array
    {
        $card = $this->cards->findById($cardId);
        if ($card === null) {
            return [];
        }

        return array_values(
            array_map(
                static fn(User $user): UuidInterface => $user->id(),
                $card->assignees()->toArray(),
            )
        );
    }

    public function creatorId(UuidInterface $cardId): ?UuidInterface
    {
        return $this->cards->findById($cardId)?->creatorId();
    }

    public function projectId(UuidInterface $cardId): ?UuidInterface
    {
        $card = $this->cards->findById($cardId);
        if ($card === null) {
            return null;
        }

        $column = $this->columns->findById($card->columnId());
        if ($column === null) {
            return null;
        }

        return $this->boards->findById($column->boardId())?->projectId();
    }
}
