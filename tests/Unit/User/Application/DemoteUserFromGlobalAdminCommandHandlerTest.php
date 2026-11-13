<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\DemoteUserFromGlobalAdminCommand;
use App\User\Application\MessageHandler\DemoteUserFromGlobalAdminCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DemoteUserFromGlobalAdminCommandHandlerTest extends TestCase
{
    public function testUnknownUser(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('nobody@example.com')
            ->willReturn(null);
        $repo->expects(self::never())->method('save');

        $handler = new DemoteUserFromGlobalAdminCommandHandler($repo);

        $this->expectException(UserNotFoundException::class);
        $handler->__invoke(new DemoteUserFromGlobalAdminCommand(' NOBODY@Example.COM '));
    }

    public function testDemotesAndSavesUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->promoteToGlobalAdmin();

        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('alice@example.com')
            ->willReturn($user);
        $repo->expects(self::once())
            ->method('save')
            ->with($user);

        $handler = new DemoteUserFromGlobalAdminCommandHandler($repo);
        $result = $handler->__invoke(new DemoteUserFromGlobalAdminCommand(' ALICE@Example.com '));

        self::assertSame($user, $result);
        self::assertFalse($user->isGlobalAdmin());
        self::assertSame(['ROLE_USER'], $user->roles());
    }
}
