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
        $missingId = (string) Uuid::uuid4();
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(Uuid::fromString($missingId))
            ->willReturn(null);
        $repo->expects(self::never())->method('save');

        $handler = new PromoteUserToGlobalAdminCommandHandler($repo);

        $this->expectException(UserNotFoundException::class);
        $handler->__invoke(new PromoteUserToGlobalAdminCommand($missingId));
    }

    public function testPromotesAndSavesUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);
        $repo->expects(self::once())
            ->method('save')
            ->with($user);

        $handler = new PromoteUserToGlobalAdminCommandHandler($repo);
        $result = $handler->__invoke(new PromoteUserToGlobalAdminCommand((string) $user->id()));

        self::assertSame($user, $result);
        self::assertTrue($user->isGlobalAdmin());
        self::assertSame(['ROLE_USER', 'ROLE_GLOBAL_ADMIN'], $user->roles());
    }
}
