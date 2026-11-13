<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\ConfirmEmailCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\ConfirmationTokenInvalidException;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ConfirmEmailCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(ConfirmEmailCommand $command): User
    {
        $tokenHash = hash('sha256', trim($command->token));
        $user = $this->userRepository->findByConfirmationTokenHash($tokenHash);

        if ($user === null) {
            throw new ConfirmationTokenInvalidException('This confirmation link is invalid or has already been used.');
        }

        $user->confirmWithToken($tokenHash, new \DateTimeImmutable());
        $this->userRepository->save($user);

        return $user;
    }
}
