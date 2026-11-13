<?php

declare(strict_types=1);

namespace App\Project\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateProjectCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        public string $actorId,
    ) {
    }
}
