<?php

declare(strict_types=1);

namespace App\User\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

final class ListUsersQuery implements Query
{
    public function __construct(
        public string $actorId,
    ) {
    }
}
