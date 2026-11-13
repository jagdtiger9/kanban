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
        $missingId = (string) Uuid::uuid4();
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(Uuid::fromString($missingId))
            ->willReturn(null);
        $repo->expects(self::never())->method('save');

        $handler = new DemoteUserFromGlobalAdminCommandHandler($repo);

        $this->expectException(UserNotFoundException::class);
        $handler->__invoke(new DemoteUserFromGlobalAdminCommand($missingId));
    }

    public function testDemotesAndSavesUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->promoteToGlobalAdmin();

        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);
        $repo->expects(self::once())
            ->method('save')
            ->with($user);

        $handler = new DemoteUserFromGlobalAdminCommandHandler($repo);
        $result = $handler->__invoke(new DemoteUserFromGlobalAdminCommand((string) $user->id()));

        self::assertSame($user, $result);
        self::assertFalse($user->isGlobalAdmin());
        self::assertSame(['ROLE_USER'], $user->roles());
    }
}
