<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\ConfirmUserAccountCommand;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ConfirmUserAccountCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private GlobalAdminGuard $globalAdminGuard,
    ) {
    }

    public function __invoke(ConfirmUserAccountCommand $command): User
    {
        $this->globalAdminGuard->requireGlobalAdmin(Uuid::fromString($command->actorId));

        $user = $this->userRepository->findById(Uuid::fromString($command->userId));
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $user->confirmAccount();
        $this->userRepository->save($user);

        return $user;
    }
}
