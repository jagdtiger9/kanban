<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\PromoteUserToGlobalAdminCommand;
use App\User\Application\MessageHandler\PromoteUserToGlobalAdminCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PromoteUserToGlobalAdminCommandHandlerTest extends TestCase
{
    public function testUnknownUser(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('nobody@example.com')
            ->willReturn(null);
        $repo->expects(self::never())->method('save');

        $handler = new PromoteUserToGlobalAdminCommandHandler($repo);

        $this->expectException(UserNotFoundException::class);
        $handler->__invoke(new PromoteUserToGlobalAdminCommand(' NOBODY@Example.COM '));
    }

    public function testPromotesAndSavesUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('alice@example.com')
            ->willReturn($user);
        $repo->expects(self::once())
            ->method('save')
            ->with($user);

        $handler = new PromoteUserToGlobalAdminCommandHandler($repo);
        $result = $handler->__invoke(new PromoteUserToGlobalAdminCommand(' ALICE@Example.com '));

        self::assertSame($user, $result);
        self::assertTrue($user->isGlobalAdmin());
        self::assertSame(['ROLE_USER', 'ROLE_GLOBAL_ADMIN'], $user->roles());
    }
}
