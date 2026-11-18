<?php

declare(strict_types=1);

namespace App\Call\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class SendCallSignalCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9-]{8,64}$/')]
        public string $sessionId = '',
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $toUserId = '',
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9-]{8,64}$/')]
        public string $toSessionId = '',
        #[Assert\Choice(choices: ['offer', 'answer', 'ice', 'state'])]
        public string $kind = '',
        /** @var array<string, mixed> */
        public array $data = [],
        public string $actorId = '',
    ) {
    }
}
