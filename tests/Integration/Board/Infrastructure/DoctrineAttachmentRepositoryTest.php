<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineAttachmentRepositoryTest extends DatabaseTestCase
{
    private BoardRepository $boards;
    private ColumnRepository $columns;
    private CardRepository $cards;
    private AttachmentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boards = $this->repo(BoardRepository::class);
        $this->columns = $this->repo(ColumnRepository::class);
        $this->cards = $this->repo(CardRepository::class);
        $this->repo = $this->repo(AttachmentRepository::class);
    }

    public function testSavesAndFindsCardAndCommentAttachments(): void
    {
        $card = $this->createCard();
        $commentId = Uuid::uuid4();

        $cardAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'photo.png',
            'attachments/a/photo.png',
            'attachments/a/thumb.webp',
            'image/png',
            2048,
            640,
            480,
        );
        $this->repo->save($cardAttachment);
        usleep(2000);

        $commentAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            $commentId,
            Uuid::uuid4(),
            'notes.txt',
            'attachments/b/notes.txt',
            null,
            'text/plain',
            12,
        );
        $this->repo->save($commentAttachment);
        usleep(2000);

        $secondCardAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'spec.pdf',
            'attachments/c/spec.pdf',
            null,
            'application/pdf',
            4096,
        );
        $this->repo->save($secondCardAttachment);
        $this->em()->clear();

        $cardAttachments = $this->repo->findCardAttachments($card->id());
        self::assertSame(['photo.png', 'spec.pdf'], $this->names($cardAttachments));

        $commentAttachments = $this->repo->findByComment($commentId);
        self::assertSame(['notes.txt'], $this->names($commentAttachments));

        $allAttachments = $this->repo->findByCard($card->id());
        self::assertSame(['photo.png', 'notes.txt', 'spec.pdf'], $this->names($allAttachments));

        self::assertSame(
            ['files' => 3, 'bytes' => 2048 + 12 + 4096],
            $this->repo->usageByCard($card->id()),
        );

        $loaded = $this->repo->findById($cardAttachment->id());
        self::assertNotNull($loaded);
        self::assertSame($card->id()->toString(), $loaded->cardId()->toString());
        self::assertNull($loaded->commentId());
        self::assertSame('attachments/a/photo.png', $loaded->storageKey());
        self::assertSame('attachments/a/thumb.webp', $loaded->thumbnailStorageKey());
        self::assertSame('image/png', $loaded->mimeType());
        self::assertSame(2048, $loaded->sizeBytes());
        self::assertSame(640, $loaded->width());
        self::assertSame(480, $loaded->height());
    }

    public function testAllStorageKeysIncludesOriginalsAndThumbnails(): void
    {
        $card = $this->createCard();
        $this->repo->save(Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'photo.png',
            'attachments/a/photo.png',
            'attachments/a/thumb.webp',
            'image/png',
            2048,
        ));
        $this->repo->save(Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'notes.txt',
            'attachments/b/notes.txt',
            null,
            'text/plain',
            12,
        ));
        $this->em()->clear();

        $keys = $this->repo->allStorageKeys();
        sort($keys);

        self::assertSame(
            ['attachments/a/photo.png', 'attachments/a/thumb.webp', 'attachments/b/notes.txt'],
            $keys,
        );
    }

    public function testRemove(): void
    {
        $card = $this->createCard();
        $attachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'photo.png',
            'attachments/a/photo.png',
            null,
            'image/png',
            2048,
        );
        $this->repo->save($attachment);
        $this->em()->clear();

        $loaded = $this->repo->findById($attachment->id());
        self::assertNotNull($loaded);
        $this->repo->remove($loaded);
        $this->em()->clear();

        self::assertNull($this->repo->findById($attachment->id()));
        self::assertSame([], $this->repo->findCardAttachments($card->id()));
        self::assertSame(['files' => 0, 'bytes' => 0], $this->repo->usageByCard($card->id()));
    }

    private function createCard(): Card
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $this->boards->save($board);
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $this->columns->save($column);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $this->cards->save($card);

        return $card;
    }

    /**
     * @param list<Attachment> $attachments
     *
     * @return list<string>
     */
    private function names(array $attachments): array
    {
        return array_map(
            static fn(Attachment $attachment): string => $attachment->originalName(),
            $attachments,
        );
    }
}
