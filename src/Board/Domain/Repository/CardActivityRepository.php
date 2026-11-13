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
}
