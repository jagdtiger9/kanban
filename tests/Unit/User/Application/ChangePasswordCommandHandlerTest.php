<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\ChangePasswordCommand;
use App\User\Application\MessageHandler\ChangePasswordCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\CurrentPasswordInvalidException;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class ChangePasswordCommandHandlerTest extends TestCase
{
    public function testChangesPasswordWithValidCurrentPassword(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'old-hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->with($user->id())->willReturn($user);
        $repo->expects(self::once())->method('save')->with($user);

        (new ChangePasswordCommandHandler($repo, $this->hasher(valid: true, newHash: 'new-hash')))
            ->__invoke(new ChangePasswordCommand('secret123', 'newsecret123', 'newsecret123', (string) $user->id()));

        self::assertSame('new-hash', $user->passwordHash());
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'old-hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($user);
        $repo->expects(self::never())->method('save');

        $this->expectException(CurrentPasswordInvalidException::class);
        (new ChangePasswordCommandHandler($repo, $this->hasher(valid: false)))
            ->__invoke(new ChangePasswordCommand('wrong', 'newsecret123', 'newsecret123', (string) $user->id()));
    }

    public function testConfirmationMismatchIsRejected(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'old-hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn($user);
        $repo->expects(self::never())->method('save');

        $this->expectException(PasswordsDoNotMatchException::class);
        (new ChangePasswordCommandHandler($repo, $this->hasher(valid: true, newHash: 'new-hash')))
            ->__invoke(new ChangePasswordCommand('secret123', 'newsecret123', 'different', (string) $user->id()));
    }

    public function testUnknownUserIsRejected(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn(null);
        $repo->expects(self::never())->method('save');

        $this->expectException(UserNotFoundException::class);
        (new ChangePasswordCommandHandler($repo, $this->createMock(PasswordHasherFactoryInterface::class)))
            ->__invoke(new ChangePasswordCommand('secret123', 'newsecret123', 'newsecret123', (string) Uuid::uuid4()));
    }

    private function hasher(bool $valid, string $newHash = 'new-hash'): PasswordHasherFactoryInterface
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('verify')->willReturn($valid);
        $hasher->method('hash')->willReturn($newHash);

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->with(User::class)->willReturn($hasher);

        return $factory;
    }
}
