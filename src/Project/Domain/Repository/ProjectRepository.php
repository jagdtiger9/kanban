<?php

declare(strict_types=1);

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\Project;
use Ramsey\Uuid\UuidInterface;

interface ProjectRepository
{
    public function save(Project $project): void;

    public function findById(UuidInterface $id): ?Project;

    /**
     * All projects the user is a member of (archived included),
     * newest first.
     *
     * @return list<Project>
     */
    public function findByMember(UuidInterface $userId): array;

    /**
     * All projects (archived included), newest first.
     *
     * @return list<Project>
     */
    public function findAll(): array;

    public function remove(Project $project): void;
}
