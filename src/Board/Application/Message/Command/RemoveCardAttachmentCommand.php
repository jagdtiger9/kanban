<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class RemoveCardAttachmentCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $attachmentId,
        public string $actorId = '',
    ) {
    }
}
