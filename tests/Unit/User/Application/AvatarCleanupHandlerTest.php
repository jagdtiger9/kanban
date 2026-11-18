<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Application\Storage\FileStorage;
use App\User\Application\MessageHandler\AvatarCleanupHandler;
use App\User\Domain\Event\UserAvatarChanged;
use App\User\Domain\Event\UserAvatarRemoved;
use PHPUnit\Framework\TestCase;

final class AvatarCleanupHandlerTest extends TestCase
{
    public function testChangedAvatarDeletesThePreviousFile(): void
    {
        $storage = $this->createMock(FileStorage::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with('avatars/user-id/old.webp');

        (new AvatarCleanupHandler($storage))->onAvatarChanged(
            new UserAvatarChanged('user-id', 'avatars/user-id/old.webp', 'avatars/user-id/new.webp'),
        );
    }

    public function testFirstAvatarDeletesNothing(): void
    {
        $storage = $this->createMock(FileStorage::class);
        $storage->expects($this->never())->method('delete');

        (new AvatarCleanupHandler($storage))->onAvatarChanged(
            new UserAvatarChanged('user-id', null, 'avatars/user-id/new.webp'),
        );
    }

    public function testRemovedAvatarDeletesTheFile(): void
    {
        $storage = $this->createMock(FileStorage::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with('avatars/user-id/avatar.webp');

        (new AvatarCleanupHandler($storage))->onAvatarRemoved(
            new UserAvatarRemoved('user-id', 'avatars/user-id/avatar.webp'),
        );
    }
}
