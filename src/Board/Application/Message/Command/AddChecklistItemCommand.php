<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use App\Board\Domain\Entity\Card;
use Symfony\Component\Validator\Constraints as Assert;

final class AddChecklistItemCommand implements Command
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\NotBlank]
        #[Assert\Length(max: Card::CHECKLIST_TEXT_MAX_LENGTH)]
        public string $text = '',
        public string $actorId = '',
    ) {
    }
}
