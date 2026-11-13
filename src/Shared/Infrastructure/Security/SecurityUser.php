<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\User\Domain\Entity\User;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security adapter over the domain user, keeping Symfony's contracts out of the
 * entity. The remember-me signature reads password/rolesString and the login
 * link reads id, hence the getter shape below.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private function __construct(
        private readonly User $user,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user);
    }

    public function user(): User
    {
        return $this->user;
    }

    public function getId(): UuidInterface
    {
        return $this->user->id();
    }

    public function getUserIdentifier(): string
    {
        return $this->user->email();
    }

    public function getPassword(): string
    {
        return $this->user->passwordHash();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->user->roles();
    }

    public function getRolesString(): string
    {
        return json_encode($this->getRoles(), JSON_THROW_ON_ERROR);
    }

    public function eraseCredentials(): void
    {
        // the password participates in the remember-me signature, so it must
        // survive the request to keep the token verifiable
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // id-only: sessions survive role/password changes and the refreshed user
        // carries the new roles. The token's own role list is frozen at login, so
        // RoleVoter checks go stale — read isGlobalAdmin()/roles() off the user instead.
        return $user instanceof self && $this->user->id()->equals($user->user()->id());
    }
}
