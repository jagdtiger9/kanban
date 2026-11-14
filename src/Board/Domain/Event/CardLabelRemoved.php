<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class CardLabelRemoved implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $cardId,
        public string $labelId,
        public string $labelName,
        ?string $actorId,
    ) {
        $this->initEvent($cardId, $actorId);
    }
}
