<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class ColumnRenamed implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $boardId,
        public string $columnId,
        ?string $actorId,
    ) {
        $this->initEvent($columnId, $actorId);
    }
}
