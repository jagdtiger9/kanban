<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\CardView;
use Ramsey\Uuid\UuidInterface;

interface CardViewRepository
{
    public function save(CardView $view): void;

    public function findByUserAndCard(UuidInterface $userId, UuidInterface $cardId): ?CardView;
}
