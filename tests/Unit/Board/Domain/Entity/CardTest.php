<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardExecutorAssigned;
use App\Board\Domain\Event\CardExecutorUnassigned;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Exception\CardLinkException;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardTest extends TestCase
{
    private function createCard(int $position = 0): Card
    {
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), '  Title  ', 'Description', $position);
        $card->releaseEvents();

        return $card;
    }

    public function testCreateTrimsTitleAndKeepsDescription(): void
    {
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), '  Title  ', 'Description', 0);

        self::assertSame('Title', $card->title());
        self::assertSame('Description', $card->description());
        self::assertFalse($card->isArchived());
        self::assertNull($card->dueDate());
    }

    public function testCreateRecordsCardCreatedEvent(): void
    {
        $cardId = Uuid::uuid4();
        $creatorId = Uuid::uuid4();

        $card = Card::create($cardId, Uuid::uuid4(), $creatorId, 'Title', '', 0);

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardCreated::class, $events[0]);
        self::assertSame($cardId->toString(), $events[0]->cardId);
        self::assertSame($creatorId->toString(), $events[0]->actorId);
    }

    public function testCreateRejectsEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), '  ', '', 0);
    }

    public function testCreateRejectsNegativePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), 'Title', '', -1);
    }

    public function testChangeTitleRecordsEvent(): void
    {
        $card = $this->createCard();
        $actorId = Uuid::uuid4();

        $card->changeTitle('New Title', $actorId);

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardFieldChanged::class, $events[0]);
        self::assertSame('title', $events[0]->field);
        self::assertSame('Title', $events[0]->oldValue);
        self::assertSame('New Title', $events[0]->newValue);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertSame('New Title', $card->title());
    }

    public function testChangeTitleToSameRecordsNoEvent(): void
    {
        $card = $this->createCard();

        $card->changeTitle('Title');

        self::assertSame([], $card->releaseEvents());
    }

    public function testChangeDescriptionPreservesMarkdownTrailingSpaces(): void
    {
        $card = $this->createCard();

        $card->changeDescription("line  \nnext");

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardFieldChanged::class, $events[0]);
        self::assertSame('description', $events[0]->field);
        self::assertSame("line  \nnext", $card->description());

        $card->changeDescription("line  \nnext");
        self::assertSame([], $card->releaseEvents());
    }

    public function testChangeDueDateSetClearAndNoop(): void
    {
        $card = $this->createCard();
        $due = new \DateTimeImmutable('2026-12-01');

        $card->changeDueDate($due);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertSame('2026-12-01', $events[0]->newValue);
        self::assertSame('2026-12-01', $card->dueDate()->format('Y-m-d'));

        $card->changeDueDate($due);
        self::assertSame([], $card->releaseEvents());

        $card->changeDueDate(null);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertNull($events[0]->newValue);
        self::assertNull($card->dueDate());
    }

    public function testMoveToRecordsEventWithOldAndNewPositions(): void
    {
        $card = $this->createCard();
        $newColumnId = Uuid::uuid4();

        $card->moveTo($newColumnId, 2);

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardMoved::class, $events[0]);
        self::assertSame(0, $events[0]->oldPosition);
        self::assertSame(2, $events[0]->newPosition);
        self::assertSame($newColumnId->toString(), $events[0]->newColumnId);
        self::assertSame(2, $card->position());
    }

    public function testMoveToSameColumnAndPositionRecordsNoEvent(): void
    {
        $card = $this->createCard();

        $card->moveTo($card->columnId(), 0);

        self::assertSame([], $card->releaseEvents());
    }

    public function testArchiveAndRestoreAreIdempotent(): void
    {
        $card = $this->createCard();

        $card->archive();
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardArchived::class, $events[0]);
        self::assertTrue($card->isArchived());

        $card->archive();
        self::assertSame([], $card->releaseEvents());

        $card->restore();
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardRestored::class, $events[0]);
        self::assertFalse($card->isArchived());

        $card->restore();
        self::assertSame([], $card->releaseEvents());
    }

    public function testAddAndRemoveLabel(): void
    {
        $card = $this->createCard();
        $label = Label::create(Uuid::uuid4(), Uuid::uuid4(), 'Bug', '#ff0000');

        $card->addLabel($label);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardLabelAdded::class, $events[0]);
        self::assertSame('Bug', $events[0]->labelName);
        self::assertCount(1, $card->labels());

        $card->addLabel($label);
        self::assertSame([], $card->releaseEvents());

        $card->removeLabel($label);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardLabelRemoved::class, $events[0]);
        self::assertCount(0, $card->labels());

        $card->removeLabel($label);
        self::assertSame([], $card->releaseEvents());
    }

    public function testAssignAndUnassign(): void
    {
        $card = $this->createCard();
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $card->assign($user);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardAssigned::class, $events[0]);
        self::assertSame('Alice', $events[0]->userName);
        self::assertCount(1, $card->assignees());

        $card->assign($user);
        self::assertSame([], $card->releaseEvents());

        $card->unassign($user);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardUnassigned::class, $events[0]);
        self::assertCount(0, $card->assignees());

        $card->unassign($user);
        self::assertSame([], $card->releaseEvents());
    }

    public function testExecutorAssignAndUnassign(): void
    {
        $card = $this->createCard();
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');

        self::assertNull($card->executorId());

        $card->assignExecutor($alice);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardExecutorAssigned::class, $events[0]);
        self::assertSame('Alice', $events[0]->userName);
        self::assertTrue($card->executorId()?->equals($alice->id()));

        $card->assignExecutor($alice);
        self::assertSame([], $card->releaseEvents());

        $card->assignExecutor($bob);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardExecutorAssigned::class, $events[0]);
        self::assertTrue($card->executorId()?->equals($bob->id()));

        $card->unassignExecutor($alice);
        self::assertSame([], $card->releaseEvents());

        $card->unassignExecutor($bob);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardExecutorUnassigned::class, $events[0]);
        self::assertSame('Bob', $events[0]->userName);
        self::assertNull($card->executorId());

        $card->unassignExecutor($bob);
        self::assertSame([], $card->releaseEvents());
    }

    public function testExecutorIsIndependentFromAssignees(): void
    {
        $card = $this->createCard();
        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $card->assignExecutor($alice);
        $card->assign($alice);
        $events = $card->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(CardExecutorAssigned::class, $events[0]);
        self::assertInstanceOf(CardAssigned::class, $events[1]);
        self::assertCount(1, $card->assignees());
        self::assertTrue($card->executorId()?->equals($alice->id()));

        $card->unassign($alice);
        self::assertCount(0, $card->assignees());
        self::assertTrue($card->executorId()?->equals($alice->id()));
    }

    public function testLinksAndUnlinksAnotherCard(): void
    {
        $card = $this->createCard();
        $other = Card::create(Uuid::uuid4(), $card->columnId(), Uuid::uuid4(), 'Other', '', 1);

        self::assertNull($card->linkedCardId());

        $card->changeLinkedCard($other->id());
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardFieldChanged::class, $events[0]);
        self::assertSame('linked_card', $events[0]->field);
        self::assertNull($events[0]->oldValue);
        self::assertSame((string) $other->id(), $events[0]->newValue);
        self::assertTrue($card->linkedCardId()?->equals($other->id()));

        $card->changeLinkedCard($other->id());
        self::assertSame([], $card->releaseEvents());

        $card->changeLinkedCard(null);
        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertSame('linked_card', $events[0]->field);
        self::assertSame((string) $other->id(), $events[0]->oldValue);
        self::assertNull($events[0]->newValue);
        self::assertNull($card->linkedCardId());

        $card->changeLinkedCard(null);
        self::assertSame([], $card->releaseEvents());
    }

    public function testRejectsLinkingCardToItself(): void
    {
        $card = $this->createCard();

        $this->expectException(CardLinkException::class);

        $card->changeLinkedCard($card->id());
    }
}
