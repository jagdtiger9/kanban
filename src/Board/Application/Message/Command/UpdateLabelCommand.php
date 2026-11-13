<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateLabelCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $labelId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Regex('/^#[0-9a-fA-F]{6}$/')]
        public string $color,
        public string $actorId = '',
    ) {
    }
}
