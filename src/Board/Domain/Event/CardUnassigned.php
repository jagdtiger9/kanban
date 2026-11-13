<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Ramsey\Uuid\UuidInterface;

final readonly class CardUnassigned
{
    public function __construct(
        public UuidInterface $cardId,
        public UuidInterface $userId,
        public string $userName,
        public ?UuidInterface $actorId,
    ) {
    }
}
