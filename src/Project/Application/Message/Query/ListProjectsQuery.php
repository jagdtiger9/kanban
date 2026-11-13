<?php

declare(strict_types=1);

namespace App\Project\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

final class ListProjectsQuery implements Query
{
    public function __construct(
        public string $actorId,
    ) {
    }
}
