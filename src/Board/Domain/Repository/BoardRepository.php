<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\Board;
use Ramsey\Uuid\UuidInterface;

interface BoardRepository
{
    public function save(Board $board): void;

    public function findById(UuidInterface $id): ?Board;

    public function findByProject(UuidInterface $projectId): ?Board;
}
