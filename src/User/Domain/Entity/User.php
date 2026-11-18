<?php

declare(strict_types=1);

namespace App\User\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\User\Domain\Event\UserAvatarChanged;
use App\User\Domain\Event\UserAvatarRemoved;
use App\User\Domain\Exception\ConfirmationTokenExpiredException;
use App\User\Domain\Exception\ConfirmationTokenInvalidException;
use App\User\Domain\Exception\UserAlreadyConfirmedException;
use App\User\Domain\ValueObject\UserRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
final class User implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $passwordHash;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $displayName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $avatarStorageKey;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $avatarUpdatedAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isGlobalAdmin;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isNewsMaker;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $confirmationTokenHash;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $confirmationTokenExpiresAt;

    private function __construct(
        UuidInterface $id,
        string $email,
        string $passwordHash,
        string $displayName,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->displayName = $displayName;
        $this->avatarStorageKey = null;
        $this->avatarUpdatedAt = null;
        $this->isGlobalAdmin = false;
        $this->isNewsMaker = false;
        $this->createdAt = $createdAt;
        $this->confirmedAt = null;
        $this->confirmationTokenHash = null;
        $this->confirmationTokenExpiresAt = null;
    }

    public static function create(
        UuidInterface $id,
        string $email,
        string $passwordHash,
        string $displayName,
    ): self {
        $email = strtolower(trim($email));
        $displayName = trim($displayName);

        if ($email === '' || $passwordHash === '' || $displayName === '') {
            throw new \InvalidArgumentException('Email, password hash and display name must not be empty');
        }

        return new self($id, $email, $passwordHash, $displayName, new \DateTimeImmutable());
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function avatarStorageKey(): ?string
    {
        return $this->avatarStorageKey;
    }

    public function avatarUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->avatarUpdatedAt;
    }

    public function avatarVersion(): ?string
    {
        if ($this->avatarStorageKey === null || $this->avatarUpdatedAt === null) {
            return null;
        }

        return $this->avatarUpdatedAt->format('Uu');
    }

    public function changeAvatar(string $storageKey): void
    {
        $storageKey = trim($storageKey);
        if ($storageKey === '' || strlen($storageKey) > 255) {
            throw new \InvalidArgumentException('Avatar storage key must be between 1 and 255 characters');
        }

        $previousStorageKey = $this->avatarStorageKey;
        $this->avatarStorageKey = $storageKey;
        $this->avatarUpdatedAt = new \DateTimeImmutable();

        $this->recordEvent(new UserAvatarChanged($this->id->toString(), $previousStorageKey, $storageKey));
    }

    public function removeAvatar(): void
    {
        if ($this->avatarStorageKey === null) {
            return;
        }

        $storageKey = $this->avatarStorageKey;
        $this->avatarStorageKey = null;
        $this->avatarUpdatedAt = null;

        $this->recordEvent(new UserAvatarRemoved($this->id->toString(), $storageKey));
    }

    public function isGlobalAdmin(): bool
    {
        return $this->isGlobalAdmin;
    }

    public function isNewsMaker(): bool
    {
        return $this->isNewsMaker;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirmationTokenHash(): ?string
    {
        return $this->confirmationTokenHash;
    }

    public function confirmationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->confirmationTokenExpiresAt;
    }

    public function beginEmailConfirmation(string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        if ($this->isConfirmed()) {
            throw new UserAlreadyConfirmedException('User account is already confirmed');
        }
        if ($tokenHash === '') {
            throw new \InvalidArgumentException('Confirmation token hash must not be empty');
        }

        $this->confirmationTokenHash = $tokenHash;
        $this->confirmationTokenExpiresAt = $expiresAt;
    }

    public function confirmWithToken(string $tokenHash, \DateTimeImmutable $now): void
    {
        if ($this->isConfirmed()) {
            throw new UserAlreadyConfirmedException('User account is already confirmed');
        }
        if ($this->confirmationTokenHash === null || !hash_equals($this->confirmationTokenHash, $tokenHash)) {
            throw new ConfirmationTokenInvalidException('Confirmation token is invalid');
        }
        if ($this->confirmationTokenExpiresAt === null || $now > $this->confirmationTokenExpiresAt) {
            throw new ConfirmationTokenExpiredException('Confirmation token has expired');
        }

        $this->confirmedAt = $now;
        $this->confirmationTokenHash = null;
        $this->confirmationTokenExpiresAt = null;
    }

    public function confirmAccount(): void
    {
        if ($this->isConfirmed()) {
            throw new UserAlreadyConfirmedException('User account is already confirmed');
        }

        $this->confirmedAt = new \DateTimeImmutable();
        $this->confirmationTokenHash = null;
        $this->confirmationTokenExpiresAt = null;
    }

    public function changeDisplayName(string $displayName): void
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            throw new \InvalidArgumentException('Display name must not be empty');
        }

        $this->displayName = $displayName;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        if ($passwordHash === '') {
            throw new \InvalidArgumentException('Password hash must not be empty');
        }

        $this->passwordHash = $passwordHash;
    }

    public function promoteToGlobalAdmin(): void
    {
        $this->isGlobalAdmin = true;
    }

    public function demoteFromGlobalAdmin(): void
    {
        $this->isGlobalAdmin = false;
    }

    public function promoteToNewsMaker(): void
    {
        $this->isNewsMaker = true;
    }

    public function demoteFromNewsMaker(): void
    {
        $this->isNewsMaker = false;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = [UserRole::ROLE_USER->name];
        if ($this->isGlobalAdmin) {
            $roles[] = UserRole::ROLE_GLOBAL_ADMIN->name;
        }
        if ($this->isNewsMaker) {
            $roles[] = UserRole::ROLE_NEWS_MAKER->name;
        }

        return $roles;
    }
}
