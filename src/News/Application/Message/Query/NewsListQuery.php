<?php

declare(strict_types=1);

namespace App\News\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;
use Symfony\Component\Validator\Constraints as Assert;

final class NewsListQuery implements Query
{
    public function __construct(
        #[Assert\Positive]
        public int $p = 1,
        #[Assert\Range(min: 1, max: 50)]
        public int $perPage = 20,
    ) {
    }
}
