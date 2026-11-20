<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Port;

use Ramsey\Uuid\UuidInterface;

interface ProjectMembersProvider
{
    /** @return list<UuidInterface> */
    public function memberIds(UuidInterface $projectId): array;
}
