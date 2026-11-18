<?php

declare(strict_types=1);

namespace App\Call\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

final class ListActiveCallsQuery implements Query
{
    public function __construct(
        public string $actorId = '',
    ) {
    }
}
