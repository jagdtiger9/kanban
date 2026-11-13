<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class SetCardLabelsCommand implements Command
{
    /**
     * @param list<string> $labelIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\All([new Assert\Uuid()])]
        public array $labelIds = [],
        public string $actorId = '',
    ) {
    }
}
