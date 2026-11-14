<?php

declare(strict_types=1);

namespace App\Board\Domain\Service;

use App\Board\Domain\Entity\Card;
use Ramsey\Uuid\UuidInterface;

final class CardMoveService
{
    /**
     * Moves a card to a new column/index and renumbers the positions of the
     * affected columns' cards so positions stay a dense 0..n sequence.
     *
     * @param list<Card> $oldColumnCards all cards of the card's current column, ordered by position
     * @param list<Card> $newColumnCards all cards of the destination column, ordered by position
     */
    public function move(
        Card $card,
        UuidInterface $newColumnId,
        int $targetIndex,
        array $oldColumnCards,
        array $newColumnCards,
        ?UuidInterface $actorId = null,
    ): void {
        if ($targetIndex < 0) {
            throw new \InvalidArgumentException('Target index must not be negative');
        }

        $oldColumnId = $card->columnId();

        if ($oldColumnId->equals($newColumnId)) {
            $cards = $this->withoutCard($oldColumnCards, $card);
            $index = min($targetIndex, count($cards));
            $card->moveTo($newColumnId, $index, $actorId);
            array_splice($cards, $index, 0, [$card]);
            $this->renumber($cards);

            return;
        }

        $remaining = $this->withoutCard($oldColumnCards, $card);
        $target = $newColumnCards;
        $index = min($targetIndex, count($target));
        $card->moveTo($newColumnId, $index, $actorId);
        $this->renumber($remaining);
        array_splice($target, $index, 0, [$card]);
        $this->renumber($target);
    }

    /**
     * @param list<Card> $cards
     *
     * @return list<Card>
     */
    private function withoutCard(array $cards, Card $card): array
    {
        return array_values(array_filter(
            $cards,
            static fn (Card $c): bool => !$c->id()->equals($card->id()),
        ));
    }

    /**
     * @param list<Card> $cards
     */
    private function renumber(array $cards): void
    {
        foreach ($cards as $i => $c) {
            $c->changePosition($i);
        }
    }
}
