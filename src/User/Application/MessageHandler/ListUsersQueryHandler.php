<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListUsersQueryHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return list<User>
     */
    public function __invoke(ListUsersQuery $query): array
    {
        return $this->userRepository->findAllOrderedByDisplayName();
    }
}
