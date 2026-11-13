<?php

declare(strict_types=1);

namespace App\User\Domain\Repository;

use App\User\Domain\Entity\User;
use Ramsey\Uuid\UuidInterface;

interface UserRepository
{
    public function save(User $user): void;

    public function findById(UuidInterface $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByConfirmationTokenHash(string $tokenHash): ?User;

    /**
     * Every user, alphabetically by display name, for the admin user list.
     *
     * @return list<User>
     */
    public function findAllOrderedByDisplayName(): array;

    /**
     * Case-insensitive substring match on email or display name,
     * ordered by display name, capped at 20 results.
     *
     * @return list<User>
     */
    public function findBySearchTerm(string $term): array;
}
