<?php

declare(strict_types=1);

namespace App\Call\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class DeclineCallCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId = '',
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $fromUserId = '',
        public string $actorId = '',
    ) {
    }
}
