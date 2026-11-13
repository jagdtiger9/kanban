<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\EventListener\CardActivityListener;
use App\Board\Domain\Entity\CardActivity;
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

final class CardActivityListenerTest extends TestCase
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

    private function listener(): CardActivityListener
    {
        return new CardActivityListener($this->activityRepository);
    }

    private function last(): CardActivity
    {
        self::assertCount(1, $this->saved);

        return $this->saved[0];
    }

    public function testCardCreated(): void
    {
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();

        $this->listener()->onCardCreated(new CardCreated($cardId, $actorId));

        $activity = $this->last();
        self::assertSame('card_created', $activity->type());
        self::assertTrue($activity->cardId()->equals($cardId));
        self::assertTrue($activity->actorId()?->equals($actorId));
        self::assertSame([], $activity->payload());
    }

    public function testCardUpdated(): void
    {
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();

        $this->listener()->onCardFieldChanged(new CardFieldChanged($cardId, 'title', 'Old', 'New', $actorId));

        $activity = $this->last();
        self::assertSame('card_updated', $activity->type());
        self::assertSame(['field' => 'title', 'oldValue' => 'Old', 'newValue' => 'New'], $activity->payload());
    }

    public function testCardMoved(): void
    {
        $cardId = Uuid::uuid4();
        $from = Uuid::uuid4();
        $to = Uuid::uuid4();
        $actorId = Uuid::uuid4();

        $this->listener()->onCardMoved(new CardMoved($cardId, $from, $to, 0, 2, $actorId));

        $activity = $this->last();
        self::assertSame('card_moved', $activity->type());
        self::assertSame([
            'fromColumnId' => (string) $from,
            'toColumnId' => (string) $to,
            'oldPosition' => 0,
            'newPosition' => 2,
        ], $activity->payload());
    }

    public function testCardArchivedWithoutActor(): void
    {
        $cardId = Uuid::uuid4();

        $this->listener()->onCardArchived(new CardArchived($cardId, null));

        $activity = $this->last();
        self::assertSame('card_archived', $activity->type());
        self::assertNull($activity->actorId());
        self::assertSame([], $activity->payload());
    }

    public function testCardRestored(): void
    {
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();

        $this->listener()->onCardRestored(new CardRestored($cardId, $actorId));

        $activity = $this->last();
        self::assertSame('card_restored', $activity->type());
        self::assertTrue($activity->actorId()?->equals($actorId));
    }

    public function testLabelAdded(): void
    {
        $cardId = Uuid::uuid4();
        $labelId = Uuid::uuid4();

        $this->listener()->onCardLabelAdded(new CardLabelAdded($cardId, $labelId, 'Bug', null));

        $activity = $this->last();
        self::assertSame('label_added', $activity->type());
        self::assertSame(['labelId' => (string) $labelId, 'labelName' => 'Bug'], $activity->payload());
    }

    public function testLabelRemoved(): void
    {
        $cardId = Uuid::uuid4();
        $labelId = Uuid::uuid4();

        $this->listener()->onCardLabelRemoved(new CardLabelRemoved($cardId, $labelId, 'Bug', null));

        $activity = $this->last();
        self::assertSame('label_removed', $activity->type());
        self::assertSame(['labelId' => (string) $labelId, 'labelName' => 'Bug'], $activity->payload());
    }

    public function testMemberAssigned(): void
    {
        $cardId = Uuid::uuid4();
        $userId = Uuid::uuid4();

        $this->listener()->onCardAssigned(new CardAssigned($cardId, $userId, 'Alice', null));

        $activity = $this->last();
        self::assertSame('member_assigned', $activity->type());
        self::assertSame(['userId' => (string) $userId, 'userName' => 'Alice'], $activity->payload());
    }

    public function testMemberUnassigned(): void
    {
        $cardId = Uuid::uuid4();
        $userId = Uuid::uuid4();

        $this->listener()->onCardUnassigned(new CardUnassigned($cardId, $userId, 'Alice', null));

        $activity = $this->last();
        self::assertSame('member_unassigned', $activity->type());
        self::assertSame(['userId' => (string) $userId, 'userName' => 'Alice'], $activity->payload());
    }

    public function testCommentAdded(): void
    {
        $cardId = Uuid::uuid4();
        $commentId = Uuid::uuid4();
        $authorId = Uuid::uuid4();

        $this->listener()->onCommentCreated(new CommentCreated($cardId, $commentId, $authorId));

        $activity = $this->last();
        self::assertSame('comment_added', $activity->type());
        self::assertTrue($activity->actorId()?->equals($authorId));
        self::assertSame(['commentId' => (string) $commentId], $activity->payload());
    }

    public function testCommentEdited(): void
    {
        $cardId = Uuid::uuid4();
        $commentId = Uuid::uuid4();
        $authorId = Uuid::uuid4();

        $this->listener()->onCommentEdited(new CommentEdited($cardId, $commentId, $authorId));

        $activity = $this->last();
        self::assertSame('comment_edited', $activity->type());
        self::assertTrue($activity->actorId()?->equals($authorId));
        self::assertSame(['commentId' => (string) $commentId], $activity->payload());
    }
}
