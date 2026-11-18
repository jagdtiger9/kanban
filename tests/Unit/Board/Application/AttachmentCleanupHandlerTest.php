<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\MessageHandler\AttachmentCleanupHandler;
use App\Board\Domain\Event\AttachmentRemoved;
use App\Shared\Application\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class AttachmentCleanupHandlerTest extends TestCase
{
    public function testDeletesTheFileAndItsThumbnail(): void
    {
        $storage = $this->createMock(FileStorage::class);
        $deleted = [];
        $storage->method('delete')->willReturnCallback(
            static function (string $key) use (&$deleted): void {
                $deleted[] = $key;
            },
        );

        $event = new AttachmentRemoved(
            'attachment-id',
            'card-id',
            null,
            'photo.jpg',
            'attachments/card/photo.jpg',
            'attachments/card/photo.webp',
            'actor-id',
        );

        (new AttachmentCleanupHandler($storage))->__invoke($event);

        self::assertSame(['attachments/card/photo.jpg', 'attachments/card/photo.webp'], $deleted);
    }

    public function testDeletesOnlyTheFileWhenThereIsNoThumbnail(): void
    {
        $storage = $this->createMock(FileStorage::class);
        $storage->expects($this->once())
            ->method('delete')
            ->with('attachments/card/notes.txt');

        $event = new AttachmentRemoved(
            'attachment-id',
            'card-id',
            null,
            'notes.txt',
            'attachments/card/notes.txt',
            null,
            'actor-id',
        );

        (new AttachmentCleanupHandler($storage))->__invoke($event);
    }
}
