<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Query\SearchUsersQuery;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SearchUsersQueryHandler
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    /**
     * @return list<User>
     */
    public function __invoke(SearchUsersQuery $query): array
    {
        return $this->userRepository->findBySearchTerm($query->term);
    }
}
