<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\UpdateAvatarCommand;
use App\User\Application\Service\AvatarUploader;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateAvatarCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private AvatarUploader $uploader,
    ) {
    }

    public function __invoke(UpdateAvatarCommand $command): User
    {
        $actorId = Uuid::fromString($command->actorId);
        $user = $this->userRepository->findById($actorId);
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $file = $command->file;
        if ($file === null) {
            throw new \LogicException('Avatar file must be validated before reaching the handler');
        }

        $user->changeAvatar($this->uploader->store($actorId, $file));
        $this->userRepository->save($user);

        return $user;
    }
}
