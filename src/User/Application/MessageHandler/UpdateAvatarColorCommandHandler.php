<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\UpdateAvatarColorCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateAvatarColorCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(UpdateAvatarColorCommand $command): User
    {
        $actorId = Uuid::fromString($command->actorId);
        $user = $this->userRepository->findById($actorId);
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $user->changeAvatarColor($command->color);
        $this->userRepository->save($user);

        return $user;
    }
}
