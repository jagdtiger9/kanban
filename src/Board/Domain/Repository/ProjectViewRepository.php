<?php

declare(strict_types=1);

namespace App\Board\Domain\Repository;

use App\Board\Domain\Entity\ProjectView;
use Ramsey\Uuid\UuidInterface;

interface ProjectViewRepository
{
    public function save(ProjectView $view): void;

    public function findByUserAndProject(UuidInterface $userId, UuidInterface $projectId): ?ProjectView;
}
