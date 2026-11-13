<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Application\MessageHandler\ListUsersQueryHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ListUsersQueryHandlerTest extends TestCase
{
    public function testReturnsUsersFromRepository(): void
    {
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');

        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findAllOrderedByDisplayName')
            ->willReturn([$alice, $bob]);

        self::assertSame([$alice, $bob], (new ListUsersQueryHandler($repo))->__invoke(new ListUsersQuery()));
    }
}
