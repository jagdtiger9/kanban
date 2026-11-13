<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Card;
use Ramsey\Uuid\UuidInterface;

interface CardRepository
{
    public function save(Card $card): void;

    public function findById(UuidInterface $id): ?Card;

    /** @return list<Card> ordered by position */
    public function findByColumn(UuidInterface $columnId): array;

    public function countByColumn(UuidInterface $columnId): int;
}
