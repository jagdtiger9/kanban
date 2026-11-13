<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\ConfirmUserAccountCommand;
use App\User\Application\MessageHandler\ConfirmUserAccountCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ConfirmUserAccountCommandHandlerTest extends TestCase
{
    public function testConfirmsUserAndClearsPendingToken(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(hash('sha256', 'token'), new \DateTimeImmutable('+24 hours'));

        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);
        $repo->expects(self::once())->method('save')->with($user);

        $result = (new ConfirmUserAccountCommandHandler($repo))
            ->__invoke(new ConfirmUserAccountCommand((string) $user->id()));

        self::assertSame($user, $result);
        self::assertTrue($user->isConfirmed());
        self::assertNull($user->confirmationTokenHash());
        self::assertNull($user->confirmationTokenExpiresAt());
    }

    public function testUnknownUserIsRejected(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('findById')->willReturn(null);
        $repo->expects(self::never())->method('save');

        $this->expectException(UserNotFoundException::class);
        (new ConfirmUserAccountCommandHandler($repo))
            ->__invoke(new ConfirmUserAccountCommand((string) Uuid::uuid4()));
    }
}
