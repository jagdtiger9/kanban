<?php

declare(strict_types=1);

namespace App\Call\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class CallHeartbeatCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9-]{8,64}$/')]
        public string $sessionId = '',
        public string $actorId = '',
    ) {
    }
}
