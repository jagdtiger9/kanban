<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\ResetUserPasswordCommand;
use App\User\Application\MessageHandler\ResetUserPasswordCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class ResetUserPasswordCommandHandlerTest extends TestCase
{
    public function testResetsPasswordAndSavesUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'old-hash', 'Alice');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);
        $repo->expects(self::once())
            ->method('save')
            ->with($user);

        $handler = new ResetUserPasswordCommandHandler($repo, $this->hasher('new-hash'));
        $result = $handler->__invoke(new ResetUserPasswordCommand((string) $user->id(), 'newsecret123'));

        self::assertSame($user, $result);
        self::assertSame('new-hash', $user->passwordHash());
    }

    public function testUnknownUserIsRejected(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn(null);
        $repo->expects(self::never())->method('save');

        $this->expectException(UserNotFoundException::class);
        (new ResetUserPasswordCommandHandler($repo, $this->createMock(PasswordHasherFactoryInterface::class)))
            ->__invoke(new ResetUserPasswordCommand((string) Uuid::uuid4(), 'newsecret123'));
    }

    private function hasher(string $newHash): PasswordHasherFactoryInterface
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())->method('hash')->with('newsecret123')->willReturn($newHash);

        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->with(User::class)->willReturn($hasher);

        return $factory;
    }
}
