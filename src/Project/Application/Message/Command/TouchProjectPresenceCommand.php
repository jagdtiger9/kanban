<?php

declare(strict_types=1);

namespace App\Project\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class TouchProjectPresenceCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId,
        public string $actorId,
    ) {
    }
}
