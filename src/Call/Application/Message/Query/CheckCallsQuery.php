<?php

declare(strict_types=1);

namespace App\Call\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;

/**
 * Diagnostics of the calls setup, for any signed-in user: nothing in the result
 * is secret (TURN credentials are the same short-lived ones a call hands out).
 */
final class CheckCallsQuery implements Query
{
    public function __construct(
        public string $actorId = '',
    ) {
    }
}
