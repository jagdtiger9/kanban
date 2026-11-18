<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\User\Application\Message\Command\RemoveAvatarCommand;
use App\User\Application\MessageHandler\RemoveAvatarCommandHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class RemoveAvatarCommandHandlerTest extends TestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    public function testRemovesTheAvatarAndSavesTheUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->changeAvatar('avatars/user-id/avatar.webp');
        $user->releaseEvents();
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save')->with($user);

        (new RemoveAvatarCommandHandler($this->userRepository))
            ->__invoke(new RemoveAvatarCommand((string)$user->id()));

        self::assertNull($user->avatarStorageKey());
        self::assertNull($user->avatarVersion());
    }

    public function testSavingAUserWithoutAvatarIsHarmless(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save')->with($user);

        (new RemoveAvatarCommandHandler($this->userRepository))
            ->__invoke(new RemoveAvatarCommand((string)$user->id()));

        self::assertNull($user->avatarStorageKey());
    }

    public function testThrowsWhenUserIsMissing(): void
    {
        $this->userRepository->method('findById')->willReturn(null);
        $this->userRepository->expects($this->never())->method('save');

        $this->expectException(UserNotFoundException::class);

        (new RemoveAvatarCommandHandler($this->userRepository))
            ->__invoke(new RemoveAvatarCommand((string)Uuid::uuid4()));
    }
}
