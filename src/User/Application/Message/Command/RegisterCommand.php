<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        public string $password,
        #[Assert\NotBlank]
        public string $passwordConfirmation,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $displayName,
    ) {
    }
}
