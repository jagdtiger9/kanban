<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Event\AttachmentAdded;
use App\Board\Domain\Event\AttachmentRemoved;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AttachmentTest extends TestCase
{
    private function attachment(
        ?UuidInterface $commentId = null,
        ?string $thumbnailStorageKey = null,
        ?int $width = null,
        ?int $height = null,
    ): Attachment {
        $attachment = Attachment::create(
            Uuid::uuid4(),
            Uuid::uuid4(),
            $commentId,
            Uuid::uuid4(),
            'report.pdf',
            'attachments/abc/report.pdf',
            $thumbnailStorageKey,
            'application/pdf',
            1234,
            $width,
            $height,
        );
        $attachment->releaseEvents();

        return $attachment;
    }

    public function testCreateRecordsAttachmentAddedEvent(): void
    {
        $id = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $commentId = Uuid::uuid4();
        $uploaderId = Uuid::uuid4();

        $attachment = Attachment::create(
            $id,
            $cardId,
            $commentId,
            $uploaderId,
            '  report.pdf  ',
            'attachments/abc/report.pdf',
            null,
            'application/pdf',
            1234,
        );

        self::assertSame('report.pdf', $attachment->originalName());
        self::assertSame($cardId->toString(), $attachment->cardId()->toString());
        self::assertSame($commentId->toString(), $attachment->commentId()?->toString());
        self::assertTrue($attachment->isCommentAttachment());

        $events = $attachment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AttachmentAdded::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->attachmentId);
        self::assertSame($id->toString(), $events[0]->getAggregateRootId());
        self::assertSame($cardId->toString(), $events[0]->cardId);
        self::assertSame($commentId->toString(), $events[0]->commentId);
        self::assertSame('report.pdf', $events[0]->fileName);
        self::assertSame($uploaderId->toString(), $events[0]->actorId);
    }

    public function testCardLevelAttachmentHasNoComment(): void
    {
        $attachment = $this->attachment();

        self::assertNull($attachment->commentId());
        self::assertFalse($attachment->isCommentAttachment());
    }

    public function testImageMetadata(): void
    {
        $attachment = $this->attachment(thumbnailStorageKey: 'attachments/abc/thumb.webp', width: 640, height: 480);

        self::assertSame('attachments/abc/thumb.webp', $attachment->thumbnailStorageKey());
        self::assertSame(640, $attachment->width());
        self::assertSame(480, $attachment->height());
        self::assertSame(1234, $attachment->sizeBytes());
        self::assertSame('application/pdf', $attachment->mimeType());
    }

    public function testRemoveRecordsAttachmentRemovedEvent(): void
    {
        $attachment = $this->attachment(
            commentId: Uuid::uuid4(),
            thumbnailStorageKey: 'attachments/abc/thumb.webp',
            width: 640,
            height: 480,
        );
        $actorId = Uuid::uuid4();

        $attachment->remove($actorId);

        $events = $attachment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AttachmentRemoved::class, $events[0]);
        self::assertSame($attachment->id()->toString(), $events[0]->attachmentId);
        self::assertSame($attachment->cardId()->toString(), $events[0]->cardId);
        self::assertSame($attachment->commentId()?->toString(), $events[0]->commentId);
        self::assertSame('report.pdf', $events[0]->fileName);
        self::assertSame('attachments/abc/report.pdf', $events[0]->storageKey);
        self::assertSame('attachments/abc/thumb.webp', $events[0]->thumbnailStorageKey);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    #[DataProvider('invalidMetadata')]
    public function testCreateRejectsInvalidMetadata(array $overrides): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Attachment::create(
            Uuid::uuid4(),
            Uuid::uuid4(),
            null,
            Uuid::uuid4(),
            $overrides['originalName'] ?? 'report.pdf',
            $overrides['storageKey'] ?? 'attachments/abc/report.pdf',
            $overrides['thumbnailStorageKey'] ?? null,
            $overrides['mimeType'] ?? 'application/pdf',
            $overrides['sizeBytes'] ?? 1234,
            $overrides['width'] ?? null,
            $overrides['height'] ?? null,
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'empty name' => [['originalName' => '   ']];
        yield 'overlong name' => [['originalName' => str_repeat('a', 256)]];
        yield 'empty storage key' => [['storageKey' => '   ']];
        yield 'overlong storage key' => [['storageKey' => 'a/'.str_repeat('b', 254)]];
        yield 'empty thumbnail key' => [['thumbnailStorageKey' => '   ']];
        yield 'empty mime type' => [['mimeType' => '   ']];
        yield 'zero size' => [['sizeBytes' => 0]];
        yield 'negative size' => [['sizeBytes' => -1]];
        yield 'width without height' => [['width' => 640]];
        yield 'height without width' => [['height' => 480]];
        yield 'zero width' => [['width' => 0, 'height' => 480]];
        yield 'negative height' => [['width' => 640, 'height' => -1]];
    }
}
