<?php

declare(strict_types=1);

namespace App\User\Domain\Entity;

use App\User\Domain\ValueObject\UserRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
final class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $passwordHash;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $displayName;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isGlobalAdmin;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

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
        $this->isGlobalAdmin = false;
        $this->createdAt = $createdAt;
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

    public function isGlobalAdmin(): bool
    {
        return $this->isGlobalAdmin;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = [UserRole::ROLE_USER->name];
        if ($this->isGlobalAdmin) {
            $roles[] = UserRole::ROLE_GLOBAL_ADMIN->name;
        }

        return $roles;
    }
}
