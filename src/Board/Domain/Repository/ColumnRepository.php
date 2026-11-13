<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Column;
use Ramsey\Uuid\UuidInterface;

interface ColumnRepository
{
    public function save(Column $column): void;

    public function findById(UuidInterface $id): ?Column;

    /** @return list<Column> ordered by position */
    public function findByBoard(UuidInterface $boardId): array;

    public function remove(Column $column): void;
}
