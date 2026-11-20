<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

final class CountUnreadUserEventsQuery implements Query
{
    public function __construct(
        public string $actorId = '',
    ) {
    }
}
