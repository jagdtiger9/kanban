<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        public string $currentPassword,
        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 100)]
        public string $newPassword,
        #[Assert\NotBlank]
        public string $newPasswordConfirmation,
        public string $actorId,
    ) {
    }
}
