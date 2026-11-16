<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\UuidInterface;

final class GlobalAdminGuard
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function requireGlobalAdmin(UuidInterface $actorId): void
    {
        $user = $this->userRepository->findById($actorId);
        if ($user === null || !$user->isGlobalAdmin()) {
            throw new AccessForbiddenException('Only global admins can perform this action');
        }
    }
}
