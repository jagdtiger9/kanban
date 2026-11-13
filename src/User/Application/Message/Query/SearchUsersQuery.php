<?php

declare(strict_types=1);

namespace App\User\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;
use Symfony\Component\Validator\Constraints as Assert;

final class SearchUsersQuery implements Query
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public string $term = '',
    ) {
    }
}
