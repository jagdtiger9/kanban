<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class ProjectRestored implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $projectId,
        string $actorId,
    ) {
        $this->initEvent($projectId, $actorId);
    }
}
