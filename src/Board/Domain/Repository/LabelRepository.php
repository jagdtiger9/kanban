<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Label;
use Ramsey\Uuid\UuidInterface;

interface LabelRepository
{
    public function save(Label $label): void;

    public function findById(UuidInterface $id): ?Label;

    /** @return list<Label> */
    public function findByBoard(UuidInterface $boardId): array;

    public function remove(Label $label): void;
}
