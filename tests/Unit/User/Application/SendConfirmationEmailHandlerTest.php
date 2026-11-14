<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\MessageHandler\SendConfirmationEmailHandler;
use App\User\Application\Service\ConfirmationEmailSender;
use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SendConfirmationEmailHandlerTest extends TestCase
{
    public function testIssuesTokenAndSendsEmailForUnconfirmedUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (UuidInterface $id): bool => $id->toString() === $user->id()->toString()))
            ->willReturn($user);

        $saved = null;
        $repo->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $savedUser) use (&$saved): void {
                $saved = $savedUser;
            });

        $sentToken = null;
        $sender = $this->createMock(ConfirmationEmailSender::class);
        $sender->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (string $email, string $displayName, string $token) use (&$sentToken): void {
                self::assertSame('alice@example.com', $email);
                self::assertSame('Alice', $displayName);
                $sentToken = $token;
            });

        $before = new \DateTimeImmutable('+24 hours');
        $handler = new SendConfirmationEmailHandler($repo, $sender);
        $handler->onUserRegistered(new UserRegistered($user->id()->toString(), 'alice@example.com', 'Alice'));
        $after = new \DateTimeImmutable('+24 hours');

        self::assertSame($user, $saved);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sentToken);
        self::assertSame(hash('sha256', $sentToken), $user->confirmationTokenHash());
        self::assertNotNull($user->confirmationTokenExpiresAt());
        self::assertGreaterThan($before, $user->confirmationTokenExpiresAt());
        self::assertLessThan($after, $user->confirmationTokenExpiresAt());
        self::assertFalse($user->isConfirmed());
    }

    public function testIgnoresMissingUser(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn(null);
        $repo->expects(self::never())->method('save');
        $sender = $this->createMock(ConfirmationEmailSender::class);
        $sender->expects(self::never())->method('send');

        $handler = new SendConfirmationEmailHandler($repo, $sender);
        $handler->onUserRegistered(new UserRegistered(Uuid::uuid4()->toString(), 'alice@example.com', 'Alice'));
    }

    public function testIgnoresAlreadyConfirmedUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->confirmAccount();

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($user);
        $repo->expects(self::never())->method('save');
        $sender = $this->createMock(ConfirmationEmailSender::class);
        $sender->expects(self::never())->method('send');

        $handler = new SendConfirmationEmailHandler($repo, $sender);
        $handler->onUserRegistered(new UserRegistered($user->id()->toString(), 'alice@example.com', 'Alice'));

        self::assertNull($user->confirmationTokenHash());
    }
}
