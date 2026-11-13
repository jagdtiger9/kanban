<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Query;

use Aljerom\SymfonyBoilerplate\CQRS\Query;
use Symfony\Component\Validator\Constraints as Assert;

final class GetBoardQuery implements Query
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId,
        public string $actorId,
        public bool $includeArchived = false,
    ) {
    }
}
