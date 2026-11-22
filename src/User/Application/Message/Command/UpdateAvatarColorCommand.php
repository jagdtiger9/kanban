<?php

declare(strict_types=1);

namespace App\User\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAvatarColorCommand implements Command
{
    public function __construct(
        #[Assert\Regex('/^#[0-9a-fA-F]{6}$/')]
        public ?string $color = null,
        public string $actorId = '',
    ) {
    }
}
