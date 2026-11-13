<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\PromoteUserToGlobalAdminCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PromoteUserToGlobalAdminCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(PromoteUserToGlobalAdminCommand $command): User
    {
        $user = $this->userRepository->findByEmail(strtolower(trim($command->email)));
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $user->promoteToGlobalAdmin();
        $this->userRepository->save($user);

        return $user;
    }
}
