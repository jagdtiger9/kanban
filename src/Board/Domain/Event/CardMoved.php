<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class CardMoved implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $cardId,
        public string $oldColumnId,
        public string $newColumnId,
        public int $oldPosition,
        public int $newPosition,
        ?string $actorId,
    ) {
        $this->initEvent($cardId, $actorId);
    }
}
