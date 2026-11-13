<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\RegisterCommand;
use App\User\Application\MessageHandler\RegisterCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserAlreadyRegisteredException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class RegisterCommandHandlerTest extends TestCase
{
    public function testPasswordsDoNotMatch(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $repo->expects(self::never())->method('save');
        $factory->expects(self::never())->method('getPasswordHasher');

        $handler = new RegisterCommandHandler($repo, $factory);

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

        $handler = new RegisterCommandHandler($repo, $this->createMock(PasswordHasherFactoryInterface::class));

        $this->expectException(UserAlreadyRegisteredException::class);
        $handler->__invoke(new RegisterCommand(' ALICE@Example.COM ', 'secret123', 'secret123', 'Alice'));
    }

    public function testRegistersUserWithHashedPassword(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

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

        $handler = new RegisterCommandHandler($repo, $factory);
        $result = $handler->__invoke(new RegisterCommand(' ALICE@Example.com ', 'secret123', 'secret123', ' Alice '));

        self::assertSame($saved, $result);
        self::assertSame('alice@example.com', $result->email());
        self::assertSame('Alice', $result->displayName());
        self::assertSame('hashed-secret123', $result->passwordHash());
        self::assertFalse($result->isGlobalAdmin());
    }
}
