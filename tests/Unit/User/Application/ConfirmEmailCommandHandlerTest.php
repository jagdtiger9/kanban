<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\ConfirmEmailCommand;
use App\User\Application\MessageHandler\ConfirmEmailCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\ConfirmationTokenExpiredException;
use App\User\Domain\Exception\ConfirmationTokenInvalidException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ConfirmEmailCommandHandlerTest extends TestCase
{
    private function pendingUser(string $token, string $expiresAt = '+24 hours'): User
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->beginEmailConfirmation(hash('sha256', $token), new \DateTimeImmutable($expiresAt));

        return $user;
    }

    public function testConfirmsUserWithValidToken(): void
    {
        $user = $this->pendingUser('secret-token');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByConfirmationTokenHash')
            ->with(hash('sha256', 'secret-token'))
            ->willReturn($user);
        $repo->expects(self::once())->method('save')->with($user);

        $result = (new ConfirmEmailCommandHandler($repo))->__invoke(new ConfirmEmailCommand('  secret-token  '));

        self::assertSame($user, $result);
        self::assertTrue($user->isConfirmed());
        self::assertNull($user->confirmationTokenHash());
        self::assertNull($user->confirmationTokenExpiresAt());
    }

    public function testUnknownTokenIsRejected(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('findByConfirmationTokenHash')->willReturn(null);
        $repo->expects(self::never())->method('save');

        $this->expectException(ConfirmationTokenInvalidException::class);
        (new ConfirmEmailCommandHandler($repo))->__invoke(new ConfirmEmailCommand('unknown'));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = $this->pendingUser('secret-token', '-1 hour');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('findByConfirmationTokenHash')->willReturn($user);
        $repo->expects(self::never())->method('save');

        $this->expectException(ConfirmationTokenExpiredException::class);
        new ConfirmEmailCommandHandler($repo)->__invoke(new ConfirmEmailCommand('secret-token'));
    }

    public function testExpiredTokenLeavesUserUnconfirmed(): void
    {
        $user = $this->pendingUser('secret-token', '-1 hour');
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByConfirmationTokenHash')->willReturn($user);

        try {
            new ConfirmEmailCommandHandler($repo)->__invoke(new ConfirmEmailCommand('secret-token'));
            self::fail('Expected ConfirmationTokenExpiredException');
        } catch (ConfirmationTokenExpiredException) {
            self::assertFalse($user->isConfirmed());
        }
    }
}
