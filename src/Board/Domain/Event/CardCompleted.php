<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class CardCompleted implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $cardId,
        ?string $actorId,
    ) {
        $this->initEvent($cardId, $actorId);
    }
}
