<?php

declare(strict_types=1);

namespace App\Project\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class AddProjectMemberCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['admin', 'member'])]
        public string $role,
        public string $actorId,
    ) {
    }
}
