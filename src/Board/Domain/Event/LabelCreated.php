<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class LabelCreated implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $boardId,
        public string $labelId,
        ?string $actorId,
    ) {
        $this->initEvent($labelId, $actorId);
    }
}
