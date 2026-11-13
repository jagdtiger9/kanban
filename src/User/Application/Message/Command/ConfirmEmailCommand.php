<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmEmailCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
