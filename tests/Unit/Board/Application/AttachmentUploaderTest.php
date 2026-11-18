<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Service\AttachmentUploader;
use App\Shared\Application\Media\ImageMetadata;
use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Media\MediaPolicy;
use App\Shared\Application\Storage\FileStorage;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentUploaderTest extends TestCase
{
    private FileStorage $storage;
    private ImageProcessor $imageProcessor;
    private AttachmentUploader $uploader;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(FileStorage::class);
        $this->imageProcessor = $this->createMock(ImageProcessor::class);
        $this->uploader = new AttachmentUploader(new MediaPolicy(), $this->storage, $this->imageProcessor);
    }

    private function file(string $mimeType, string $path, int $size, string $originalName): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getPathname')->willReturn($path);
        $file->method('getSize')->willReturn($size);
        $file->method('getClientOriginalName')->willReturn($originalName);

        return $file;
    }

    public function testStoresADocumentWithoutProcessingIt(): void
    {
        $projectId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $uploaderId = Uuid::uuid4();
        $written = [];

        $this->storage->method('writeFromFile')->willReturnCallback(
            static function (string $key, string $sourcePath) use (&$written): void {
                $written[] = [$key, $sourcePath];
            },
        );
        $this->imageProcessor->expects($this->never())->method('thumbnail');

        $attachment = $this->uploader->store(
            $projectId,
            $cardId,
            null,
            $uploaderId,
            $this->file('text/plain', '/tmp/notes.txt', 12, 'notes.txt'),
        );

        self::assertSame('/tmp/notes.txt', $written[0][1]);
        self::assertMatchesRegularExpression(
            '#^attachments/'.$projectId->toString().'/'.$cardId->toString().'/[0-9a-f-]{36}\.txt$#',
            $written[0][0],
        );
        self::assertSame($written[0][0], $attachment->storageKey());
        self::assertNull($attachment->thumbnailStorageKey());
        self::assertNull($attachment->commentId());
        self::assertTrue($attachment->cardId()->equals($cardId));
        self::assertTrue($attachment->uploaderId()->equals($uploaderId));
        self::assertSame('notes.txt', $attachment->originalName());
        self::assertSame('text/plain', $attachment->mimeType());
        self::assertSame(12, $attachment->sizeBytes());
        self::assertNull($attachment->width());
        self::assertNull($attachment->height());
    }

    public function testStoresAnImageThumbnailAndDimensions(): void
    {
        $projectId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $written = [];

        $this->storage->method('writeFromFile')->willReturnCallback(
            static function (string $key, string $sourcePath) use (&$written): void {
                $written[] = [$key, $sourcePath];
            },
        );
        $this->imageProcessor->expects($this->once())
            ->method('thumbnail')
            ->with('/tmp/photo.jpg')
            ->willReturn(new ImageMetadata(800, 600));

        $attachment = $this->uploader->store(
            $projectId,
            $cardId,
            Uuid::uuid4(),
            Uuid::uuid4(),
            $this->file('image/jpeg', '/tmp/photo.jpg', 1024, 'photo.jpg'),
        );

        self::assertCount(2, $written);
        self::assertMatchesRegularExpression('#\.jpg$#', $written[0][0]);
        self::assertMatchesRegularExpression('#\.webp$#', $written[1][0]);
        self::assertSame($written[1][0], $attachment->thumbnailStorageKey());
        self::assertSame(800, $attachment->width());
        self::assertSame(600, $attachment->height());
    }

    public function testWebpOriginalKeepsItsOwnKeyApartFromTheThumbnail(): void
    {
        $written = [];

        $this->storage->method('writeFromFile')->willReturnCallback(
            static function (string $key, string $sourcePath) use (&$written): void {
                $written[] = $key;
            },
        );
        $this->imageProcessor->method('thumbnail')->willReturn(new ImageMetadata(800, 600));

        $attachment = $this->uploader->store(
            Uuid::uuid4(),
            Uuid::uuid4(),
            null,
            Uuid::uuid4(),
            $this->file('image/webp', '/tmp/photo.webp', 1024, 'photo.webp'),
        );

        self::assertCount(2, $written);
        self::assertSame($attachment->storageKey(), $written[0]);
        self::assertSame($attachment->thumbnailStorageKey(), $written[1]);
        self::assertNotSame($attachment->storageKey(), $attachment->thumbnailStorageKey());
        self::assertStringEndsWith('.webp', $attachment->storageKey());
        self::assertStringEndsWith('-thumbnail.webp', $attachment->thumbnailStorageKey());
    }

    public function testSanitizesTheOriginalName(): void
    {
        $this->storage->method('writeFromFile');

        $attachment = $this->uploader->store(
            Uuid::uuid4(),
            Uuid::uuid4(),
            null,
            Uuid::uuid4(),
            $this->file('text/plain', '/tmp/x', 1, '..\\..\\weird'.chr(0).'name.txt'),
        );

        self::assertSame('weird_name.txt', $attachment->originalName());
    }

    public function testFallsBackToAGenericName(): void
    {
        $this->storage->method('writeFromFile');

        $attachment = $this->uploader->store(
            Uuid::uuid4(),
            Uuid::uuid4(),
            null,
            Uuid::uuid4(),
            $this->file('text/plain', '/tmp/x', 1, '..'),
        );

        self::assertSame('file', $attachment->originalName());
    }

    public function testRejectsAnUnsupportedMimeTypeBeforeWriting(): void
    {
        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(\InvalidArgumentException::class);

        $this->uploader->store(
            Uuid::uuid4(),
            Uuid::uuid4(),
            null,
            Uuid::uuid4(),
            $this->file('application/zip', '/tmp/x.zip', 12, 'x.zip'),
        );
    }
}
