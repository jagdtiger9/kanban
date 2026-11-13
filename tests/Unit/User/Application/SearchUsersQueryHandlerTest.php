<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Query\SearchUsersQuery;
use App\User\Application\MessageHandler\SearchUsersQueryHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SearchUsersQueryHandlerTest extends TestCase
{
    public function testDelegatesToRepository(): void
    {
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('findBySearchTerm')
            ->with('ali')
            ->willReturn([$alice, $bob]);

        $result = (new SearchUsersQueryHandler($userRepository))->__invoke(new SearchUsersQuery('ali'));

        self::assertSame([$alice, $bob], $result);
    }
}
