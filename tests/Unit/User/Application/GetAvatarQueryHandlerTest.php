<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Application\Storage\FileStorage;
use App\User\Application\Message\Query\GetAvatarQuery;
use App\User\Application\MessageHandler\GetAvatarQueryHandler;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\AvatarNotFoundException;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class GetAvatarQueryHandlerTest extends TestCase
{
    private UserRepository $userRepository;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->storage = $this->createMock(FileStorage::class);
    }

    public function testReturnsTheStorageStream(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->changeAvatar('avatars/user-id/avatar.webp');
        $this->userRepository->method('findById')->willReturn($user);
        $stream = fopen('php://temp', 'r+b');
        self::assertNotFalse($stream);
        $this->storage->expects($this->once())
            ->method('open')
            ->with('avatars/user-id/avatar.webp')
            ->willReturn($stream);

        $result = (new GetAvatarQueryHandler($this->userRepository, $this->storage))
            ->__invoke(new GetAvatarQuery((string)$user->id()));

        self::assertSame(['stream' => $stream], $result);
    }

    public function testThrowsWhenUserHasNoAvatar(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->userRepository->method('findById')->willReturn($user);
        $this->storage->expects($this->never())->method('open');

        $this->expectException(AvatarNotFoundException::class);

        (new GetAvatarQueryHandler($this->userRepository, $this->storage))
            ->__invoke(new GetAvatarQuery((string)$user->id()));
    }

    public function testThrowsWhenUserIsMissing(): void
    {
        $this->userRepository->method('findById')->willReturn(null);
        $this->storage->expects($this->never())->method('open');

        $this->expectException(UserNotFoundException::class);

        (new GetAvatarQueryHandler($this->userRepository, $this->storage))
            ->__invoke(new GetAvatarQuery((string)Uuid::uuid4()));
    }
}
