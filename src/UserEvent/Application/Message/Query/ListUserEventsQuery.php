<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;
use Symfony\Component\Validator\Constraints as Assert;

final class ListUserEventsQuery implements Query
{
    public function __construct(
        public ?string $cursor = null,
        #[Assert\Range(min: 1, max: 50)]
        public int $limit = 20,
        public string $actorId = '',
    ) {
    }
}
