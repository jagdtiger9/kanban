<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Application\MessageHandler\ListUsersQueryHandler;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ListUsersQueryHandlerTest extends TestCase
{
    public function testReturnsUsersFromRepository(): void
    {
        $actor = $this->globalAdmin();
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')
            ->with($actor->id())
            ->willReturn($actor);
        $repo->expects(self::once())
            ->method('findAllOrderedByDisplayName')
            ->willReturn([$alice, $bob]);

        $handler = new ListUsersQueryHandler($repo, new GlobalAdminGuard($repo));

        self::assertSame([$alice, $bob], $handler(new ListUsersQuery((string) $actor->id())));
    }

    public function testPlainUserIsRejected(): void
    {
        $actor = User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($actor);
        $repo->expects(self::never())->method('findAllOrderedByDisplayName');

        $this->expectException(AccessForbiddenException::class);

        (new ListUsersQueryHandler($repo, new GlobalAdminGuard($repo)))
            ->__invoke(new ListUsersQuery((string) $actor->id()));
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }
}
