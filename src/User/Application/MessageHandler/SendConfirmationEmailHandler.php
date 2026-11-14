<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Service\ConfirmationEmailSender;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class SendConfirmationEmailHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ConfirmationEmailSender $sender,
    ) {
    }

    #[AsMessageHandler]
    public function onUserRegistered(UserRegistered $event): void
    {
        $user = $this->userRepository->findById(Uuid::fromString($event->userId));
        if ($user === null || $user->isConfirmed()) {
            return;
        }

        $confirmationToken = bin2hex(random_bytes(32));
        $user->beginEmailConfirmation(hash('sha256', $confirmationToken), new \DateTimeImmutable('+24 hours'));
        $this->userRepository->save($user);

        $this->sender->send($user->email(), $user->displayName(), $confirmationToken);
    }
}
