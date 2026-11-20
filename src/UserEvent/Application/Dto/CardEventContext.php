<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Dto;

use Ramsey\Uuid\UuidInterface;

final readonly class CardEventContext
{
    public function __construct(
        private UuidInterface $projectId,
        private string $projectName,
        private string $cardTitle,
    ) {
    }

    public function projectId(): UuidInterface
    {
        return $this->projectId;
    }

    public function projectName(): string
    {
        return $this->projectName;
    }

    public function cardTitle(): string
    {
        return $this->cardTitle;
    }
}
