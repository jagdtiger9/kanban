<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class DemoteUserFromNewsMakerCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,
        public string $actorId = '',
    ) {
    }
}
