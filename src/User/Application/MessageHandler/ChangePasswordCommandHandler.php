<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\ChangePasswordCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\CurrentPasswordInvalidException;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsMessageHandler]
final class ChangePasswordCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    public function __invoke(ChangePasswordCommand $command): void
    {
        $user = $this->userRepository->findById(Uuid::fromString($command->actorId));
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        if (!$hasher->verify($user->passwordHash(), $command->currentPassword)) {
            throw new CurrentPasswordInvalidException('Current password is incorrect');
        }

        if ($command->newPassword !== $command->newPasswordConfirmation) {
            throw new PasswordsDoNotMatchException('Passwords do not match');
        }

        $user->changePasswordHash($hasher->hash($command->newPassword));
        $this->userRepository->save($user);
    }
}
