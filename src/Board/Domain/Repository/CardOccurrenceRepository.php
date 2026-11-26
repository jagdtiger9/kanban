<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\CardOccurrence;
use Ramsey\Uuid\UuidInterface;

interface CardOccurrenceRepository
{
    public function save(CardOccurrence $occurrence): void;

    public function remove(CardOccurrence $occurrence): void;

    public function findByCardAndPeriod(UuidInterface $cardId, \DateTimeImmutable $period): ?CardOccurrence;

    /**
     * @param list<UuidInterface> $cardIds
     *
     * @return array<string, list<string>> card id => completed periods ('Y-m-d')
     */
    public function findCompletedPeriodsByCards(array $cardIds): array;
}
