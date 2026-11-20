<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;

final class MarkUserEventsSeenCommand implements Command
{
    public function __construct(
        public string $actorId = '',
    ) {
    }
}
