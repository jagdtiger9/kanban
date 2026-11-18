<?php

declare(strict_types=1);

namespace App\News\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNewsPostCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $newsPostId,
        #[Assert\NotBlank]
        public string $body,
        public string $actorId = '',
    ) {
    }
}
