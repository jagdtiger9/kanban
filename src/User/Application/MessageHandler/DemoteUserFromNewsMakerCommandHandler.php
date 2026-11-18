<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\DemoteUserFromNewsMakerCommand;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DemoteUserFromNewsMakerCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private GlobalAdminGuard $globalAdminGuard,
    ) {
    }

    public function __invoke(DemoteUserFromNewsMakerCommand $command): User
    {
        $this->globalAdminGuard->requireGlobalAdmin(Uuid::fromString($command->actorId));

        $user = $this->userRepository->findById(Uuid::fromString($command->userId));
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $user->demoteFromNewsMaker();
        $this->userRepository->save($user);

        return $user;
    }
}
