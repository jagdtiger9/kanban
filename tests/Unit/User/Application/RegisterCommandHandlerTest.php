<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\RegisterCommand;
use App\User\Application\MessageHandler\RegisterCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserRegistered;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserAlreadyRegisteredException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RegisterCommandHandlerTest extends TestCase
{
    public function testPasswordsDoNotMatch(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $repo->expects(self::never())->method('save');
        $factory->expects(self::never())->method('getPasswordHasher');
        $dispatcher->expects(self::never())->method('dispatch');

        $handler = new RegisterCommandHandler($repo, $factory, $dispatcher);

        $this->expectException(PasswordsDoNotMatchException::class);
        $handler->__invoke(new RegisterCommand('alice@example.com', 'secret123', 'different', 'Alice'));
    }

    public function testAlreadyRegistered(): void
    {
        $existing = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('alice@example.com')
            ->willReturn($existing);
        $repo->expects(self::never())->method('save');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $handler = new RegisterCommandHandler($repo, $this->createMock(PasswordHasherFactoryInterface::class), $dispatcher);

        $this->expectException(UserAlreadyRegisteredException::class);
        $handler->__invoke(new RegisterCommand(' ALICE@Example.COM ', 'secret123', 'secret123', 'Alice'));
    }

    public function testRegistersUnconfirmedUserWithHashedPasswordAndDispatchesEvent(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repo->expects(self::once())
            ->method('findByEmail')
            ->with('alice@example.com')
            ->willReturn(null);
        $factory->expects(self::once())
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($hasher);
        $hasher->expects(self::once())
            ->method('hash')
            ->with('secret123')
            ->willReturn('hashed-secret123');

        $saved = null;
        $repo->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$saved): void {
                $saved = $user;
            });

        $dispatched = null;
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (UserRegistered $event) use (&$dispatched): UserRegistered {
                $dispatched = $event;

                return $event;
            });

        $before = new \DateTimeImmutable('+24 hours');
        $handler = new RegisterCommandHandler($repo, $factory, $dispatcher);
        $result = $handler->__invoke(new RegisterCommand(' ALICE@Example.com ', 'secret123', 'secret123', ' Alice '));
        $after = new \DateTimeImmutable('+24 hours');

        self::assertSame($saved, $result);
        self::assertSame('alice@example.com', $result->email());
        self::assertSame('Alice', $result->displayName());
        self::assertSame('hashed-secret123', $result->passwordHash());
        self::assertFalse($result->isGlobalAdmin());
        self::assertFalse($result->isConfirmed());

        self::assertNotNull($result->confirmationTokenHash());
        self::assertSame(64, strlen($result->confirmationTokenHash()));
        self::assertNotNull($result->confirmationTokenExpiresAt());
        self::assertGreaterThan($before, $result->confirmationTokenExpiresAt());
        self::assertLessThan($after, $result->confirmationTokenExpiresAt());

        self::assertNotNull($dispatched);
        self::assertSame('alice@example.com', $dispatched->email);
        self::assertSame('Alice', $dispatched->displayName);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $dispatched->confirmationToken);
        self::assertSame($result->confirmationTokenHash(), hash('sha256', $dispatched->confirmationToken));
    }
}
