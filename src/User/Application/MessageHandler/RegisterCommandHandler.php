<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\User\Application\Message\Command\RegisterCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserAlreadyRegisteredException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class RegisterCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterCommand $command): User
    {
        if ($command->password !== $command->passwordConfirmation) {
            throw new PasswordsDoNotMatchException('Passwords do not match');
        }

        $email = strtolower(trim($command->email));
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new UserAlreadyRegisteredException('User already registered');
        }

        $passwordHash = $this->passwordHasherFactory
            ->getPasswordHasher(User::class)
            ->hash($command->password);

        $user = User::create(Uuid::uuid4(), $email, $passwordHash, $command->displayName);
        $confirmationToken = bin2hex(random_bytes(32));
        $user->beginEmailConfirmation(hash('sha256', $confirmationToken), new \DateTimeImmutable('+24 hours'));
        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(new UserRegistered($user->email(), $user->displayName(), $confirmationToken));

        return $user;
    }
}
