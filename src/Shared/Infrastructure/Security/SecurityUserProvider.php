<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $user = $this->userRepository->findByEmail(strtolower(trim($identifier)));
        if ($user === null) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return SecurityUser::fromUser($user);
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        $fresh = $this->userRepository->findById($user->getId());
        if ($fresh === null) {
            throw new UserNotFoundException('User no longer exists.');
        }

        return SecurityUser::fromUser($fresh);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}
