<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class GlobalAdminGuardTest extends TestCase
{
    private function guardWith(?User $actorUser): GlobalAdminGuard
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($actorUser);

        return new GlobalAdminGuard($userRepository);
    }

    public function testAllowsGlobalAdmin(): void
    {
        $actor = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $actor->promoteToGlobalAdmin();

        $this->guardWith($actor)->requireGlobalAdmin($actor->id());

        self::assertTrue($actor->isGlobalAdmin());
    }

    public function testRejectsPlainUser(): void
    {
        $this->expectException(AccessForbiddenException::class);

        $this->guardWith(User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User'))
            ->requireGlobalAdmin(Uuid::uuid4());
    }

    public function testRejectsUnknownActor(): void
    {
        $this->expectException(AccessForbiddenException::class);

        $this->guardWith(null)->requireGlobalAdmin(Uuid::uuid4());
    }
}
