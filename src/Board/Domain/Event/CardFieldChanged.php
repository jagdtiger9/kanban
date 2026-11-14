<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class CardFieldChanged implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $cardId,
        public string $field,
        public ?string $oldValue,
        public ?string $newValue,
        ?string $actorId,
    ) {
        $this->initEvent($cardId, $actorId);
    }
}
