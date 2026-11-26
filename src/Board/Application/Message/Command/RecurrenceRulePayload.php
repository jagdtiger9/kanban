<?php

declare(strict_types=1);

namespace App\Board\Application\Message\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class RecurrenceRulePayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['weekly', 'monthly'])]
        public string $kind = '',
        #[Assert\All([new Assert\Type('integer'), new Assert\Range(min: 1, max: 7)])]
        public array $weekdays = [],
        #[Assert\All([new Assert\Type('integer'), new Assert\Range(min: 1, max: 31)])]
        public array $monthDays = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'weekdays' => $this->weekdays,
            'monthDays' => $this->monthDays,
        ];
    }
}
