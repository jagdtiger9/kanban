<?php

declare(strict_types=1);

namespace App\Mcp\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

/**
 * "What's new": card activity in the actor's projects after $since (default: last 24 hours).
 * Actor-scoped like ListProjectsQuery — filtering by actor replaces a guard.
 */
final class ListRecentActivityQuery implements Query
{
    public function __construct(
        public string $actorId = '',
        public string $since = '',
    ) {
    }
}
