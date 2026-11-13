<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Ramsey\Uuid\UuidInterface;

final readonly class CardMoved
{
    public function __construct(
        public UuidInterface $cardId,
        public UuidInterface $oldColumnId,
        public UuidInterface $newColumnId,
        public int $oldPosition,
        public int $newPosition,
        public ?UuidInterface $actorId,
    ) {
    }
}
