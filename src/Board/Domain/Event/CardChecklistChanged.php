<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class CardChecklistChanged implements DomainEventInterface
{
    use DomainEventTrait;

    public const string ACTION_ADDED = 'added';
    public const string ACTION_REMOVED = 'removed';
    public const string ACTION_TOGGLED = 'toggled';

    public function __construct(
        public string $cardId,
        public string $action,
        public string $itemId,
        public string $itemText,
        public bool $done,
        ?string $actorId,
    ) {
        $this->initEvent($cardId, $actorId);
    }
}
