<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\RemoveAvatarCommand;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveAvatarCommandHandler
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function __invoke(RemoveAvatarCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $user = $this->userRepository->findById($actorId);
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $user->removeAvatar();
        $this->userRepository->save($user);
    }
}
