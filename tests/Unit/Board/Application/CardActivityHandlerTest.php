<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\MessageHandler\CardActivityHandler;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Event\AttachmentAdded;
use App\Board\Domain\Event\AttachmentRemoved;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Repository\CardActivityRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardActivityHandlerTest extends TestCase
{
    private CardActivityRepository $activityRepository;

    /** @var list<CardActivity> */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->activityRepository = $this->createMock(CardActivityRepository::class);
        $this->saved = [];
        $this->activityRepository->method('save')->willReturnCallback(function (CardActivity $activity): void {
            $this->saved[] = $activity;
        });
    }

    private function handler(): CardActivityHandler
    {
        return new CardActivityHandler($this->activityRepository);
    }

    private function last(): CardActivity
    {
        self::assertCount(1, $this->saved);

        return $this->saved[0];
    }

    public function testCardCreated(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onCardCreated(new CardCreated($cardId, $actorId));

        $activity = $this->last();
        self::assertSame('card_created', $activity->type());
        self::assertSame($cardId, $activity->cardId()->toString());
        self::assertSame($actorId, $activity->actorId()?->toString());
        self::assertSame([], $activity->payload());
    }

    public function testCardUpdated(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onCardFieldChanged(new CardFieldChanged($cardId, 'title', 'Old', 'New', $actorId));

        $activity = $this->last();
        self::assertSame('card_updated', $activity->type());
        self::assertSame(['field' => 'title', 'oldValue' => 'Old', 'newValue' => 'New'], $activity->payload());
    }

    public function testCardMoved(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $from = Uuid::uuid4()->toString();
        $to = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onCardMoved(new CardMoved($cardId, $from, $to, 0, 2, $actorId));

        $activity = $this->last();
        self::assertSame('card_moved', $activity->type());
        self::assertSame([
            'fromColumnId' => $from,
            'toColumnId' => $to,
            'oldPosition' => 0,
            'newPosition' => 2,
        ], $activity->payload());
    }

    public function testCardArchivedWithoutActor(): void
    {
        $cardId = Uuid::uuid4()->toString();

        $this->handler()->onCardArchived(new CardArchived($cardId, null));

        $activity = $this->last();
        self::assertSame('card_archived', $activity->type());
        self::assertNull($activity->actorId());
        self::assertSame([], $activity->payload());
    }

    public function testCardRestored(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onCardRestored(new CardRestored($cardId, $actorId));

        $activity = $this->last();
        self::assertSame('card_restored', $activity->type());
        self::assertSame($actorId, $activity->actorId()?->toString());
    }

    public function testLabelAdded(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $labelId = Uuid::uuid4()->toString();

        $this->handler()->onCardLabelAdded(new CardLabelAdded($cardId, $labelId, 'Bug', null));

        $activity = $this->last();
        self::assertSame('label_added', $activity->type());
        self::assertSame(['labelId' => $labelId, 'labelName' => 'Bug'], $activity->payload());
    }

    public function testLabelRemoved(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $labelId = Uuid::uuid4()->toString();

        $this->handler()->onCardLabelRemoved(new CardLabelRemoved($cardId, $labelId, 'Bug', null));

        $activity = $this->last();
        self::assertSame('label_removed', $activity->type());
        self::assertSame(['labelId' => $labelId, 'labelName' => 'Bug'], $activity->payload());
    }

    public function testMemberAssigned(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();

        $this->handler()->onCardAssigned(new CardAssigned($cardId, $userId, 'Alice', null));

        $activity = $this->last();
        self::assertSame('member_assigned', $activity->type());
        self::assertSame(['userId' => $userId, 'userName' => 'Alice'], $activity->payload());
    }

    public function testMemberUnassigned(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();

        $this->handler()->onCardUnassigned(new CardUnassigned($cardId, $userId, 'Alice', null));

        $activity = $this->last();
        self::assertSame('member_unassigned', $activity->type());
        self::assertSame(['userId' => $userId, 'userName' => 'Alice'], $activity->payload());
    }

    public function testCommentAdded(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $commentId = Uuid::uuid4()->toString();
        $authorId = Uuid::uuid4()->toString();

        $this->handler()->onCommentCreated(new CommentCreated($cardId, $commentId, $authorId));

        $activity = $this->last();
        self::assertSame('comment_added', $activity->type());
        self::assertSame($authorId, $activity->actorId()?->toString());
        self::assertSame(['commentId' => $commentId], $activity->payload());
    }

    public function testCommentEdited(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $commentId = Uuid::uuid4()->toString();
        $authorId = Uuid::uuid4()->toString();

        $this->handler()->onCommentEdited(new CommentEdited($cardId, $commentId, $authorId));

        $activity = $this->last();
        self::assertSame('comment_edited', $activity->type());
        self::assertSame($authorId, $activity->actorId()?->toString());
        self::assertSame(['commentId' => $commentId], $activity->payload());
    }

    public function testCardAttachmentAdded(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $attachmentId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onAttachmentAdded(new AttachmentAdded($attachmentId, $cardId, null, 'notes.txt', $actorId));

        $activity = $this->last();
        self::assertSame('attachment_added', $activity->type());
        self::assertSame($actorId, $activity->actorId()?->toString());
        self::assertSame(['attachmentId' => $attachmentId, 'fileName' => 'notes.txt'], $activity->payload());
    }

    public function testCommentAttachmentAddedIsNotRecordedSeparately(): void
    {
        $this->handler()->onAttachmentAdded(new AttachmentAdded(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            'notes.txt',
            Uuid::uuid4()->toString(),
        ));

        self::assertSame([], $this->saved);
    }

    public function testAttachmentRemoved(): void
    {
        $cardId = Uuid::uuid4()->toString();
        $attachmentId = Uuid::uuid4()->toString();
        $commentId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        $this->handler()->onAttachmentRemoved(new AttachmentRemoved(
            $attachmentId,
            $cardId,
            $commentId,
            'notes.txt',
            'attachments/card/notes.txt',
            null,
            $actorId,
        ));

        $activity = $this->last();
        self::assertSame('attachment_removed', $activity->type());
        self::assertSame($actorId, $activity->actorId()?->toString());
        self::assertSame([
            'attachmentId' => $attachmentId,
            'fileName' => 'notes.txt',
            'commentId' => $commentId,
        ], $activity->payload());
    }
}
