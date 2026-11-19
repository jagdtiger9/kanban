<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\CardActivity;
use Ramsey\Uuid\UuidInterface;

interface CardActivityRepository
{
    public function save(CardActivity $activity): void;

    /** @return list<CardActivity> ordered by creation time */
    public function findByCard(UuidInterface $cardId): array;

    /**
     * Activities by other users newer than the card's effective seen time
     * (`GREATEST(card_views.last_seen_at, $baseline)`), grouped per card.
     *
     * @return array<string, int> card id => unseen count
     */
    public function countUnseenByBoard(UuidInterface $boardId, UuidInterface $userId, \DateTimeImmutable $baseline): array;
}
