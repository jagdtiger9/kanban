<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Symfony\Component\Validator\Constraints as Assert;

final class SetCardAssigneesCommand implements Command
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $cardId,
        #[Assert\All([new Assert\Uuid()])]
        public array $userIds = [],
        public string $actorId = '',
    ) {
    }
}
