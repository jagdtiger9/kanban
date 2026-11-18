<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application;

use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Storage\FileStorage;
use App\User\Application\Message\Command\UpdateAvatarCommand;
use App\User\Application\MessageHandler\UpdateAvatarCommandHandler;
use App\User\Application\Service\AvatarUploader;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UpdateAvatarCommandHandlerTest extends TestCase
{
    private UserRepository $userRepository;
    private FileStorage $storage;
    private ImageProcessor $images;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->storage = $this->createMock(FileStorage::class);
        $this->images = $this->createMock(ImageProcessor::class);
    }

    private function handler(): UpdateAvatarCommandHandler
    {
        return new UpdateAvatarCommandHandler(
            $this->userRepository,
            new AvatarUploader($this->storage, $this->images),
        );
    }

    private function imageFile(): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getPathname')->willReturn('/tmp/kanban-avatar.png');

        return $file;
    }

    public function testStoresTheProcessedAvatarAndUpdatesTheUser(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->userRepository->method('findById')->willReturn($user);

        $this->images->expects($this->once())
            ->method('avatar')
            ->with('/tmp/kanban-avatar.png', self::stringContains('kanban-avatar-'));
        $this->storage->expects($this->once())
            ->method('writeFromFile')
            ->with(
                self::logicalAnd(
                    self::stringStartsWith('avatars/'.$user->id()->toString().'/'),
                    self::stringEndsWith('.webp'),
                ),
                self::stringContains('kanban-avatar-'),
            );
        $this->userRepository->expects($this->once())->method('save')->with($user);

        $result = $this->handler()->__invoke(new UpdateAvatarCommand($this->imageFile(), (string)$user->id()));

        self::assertSame($user, $result);
        self::assertNotNull($result->avatarStorageKey());
        self::assertNotNull($result->avatarVersion());
    }

    public function testThrowsWhenUserIsMissing(): void
    {
        $this->userRepository->method('findById')->willReturn(null);

        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(UserNotFoundException::class);

        $this->handler()->__invoke(new UpdateAvatarCommand($this->imageFile(), (string)Uuid::uuid4()));
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->userRepository->method('findById')->willReturn($user);

        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(\LogicException::class);

        $this->handler()->__invoke(new UpdateAvatarCommand(null, (string)$user->id()));
    }
}
