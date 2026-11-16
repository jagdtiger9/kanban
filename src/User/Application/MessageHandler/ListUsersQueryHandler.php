<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListUsersQueryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private GlobalAdminGuard $globalAdminGuard,
    ) {
    }

    /**
     * @return list<User>
     */
    public function __invoke(ListUsersQuery $query): array
    {
        $this->globalAdminGuard->requireGlobalAdmin(Uuid::fromString($query->actorId));

        return $this->userRepository->findAllOrderedByDisplayName();
    }
}
