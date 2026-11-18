<?php

declare(strict_types=1);

namespace App\Project\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class SetProjectFavouriteCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $projectId,
        #[Assert\Type(type: 'bool')]
        public bool $isFavourite,
        public string $actorId,
    ) {
    }
}
