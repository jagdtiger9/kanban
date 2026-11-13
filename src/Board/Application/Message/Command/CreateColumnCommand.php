<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateColumnCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        public string $actorId,
    ) {
    }
}
