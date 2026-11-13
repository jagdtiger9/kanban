<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardTest extends TestCase
{
    private function createCard(int $position = 0): Card
    {
        return Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), '  Title  ', 'Description', $position);
    }

    public function testCreateTrimsTitleAndKeepsDescription(): void
    {
        $card = $this->createCard();

        self::assertSame('Title', $card->title());
        self::assertSame('Description', $card->description());
        self::assertFalse($card->isArchived());
        self::assertNull($card->dueDate());
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

    public function testChangeTitleEmitsEvent(): void
    {
        $card = $this->createCard();
        $actorId = Uuid::uuid4();

        $event = $card->changeTitle('New Title', $actorId);

        self::assertInstanceOf(CardFieldChanged::class, $event);
        self::assertSame('title', $event->field);
        self::assertSame('Title', $event->oldValue);
        self::assertSame('New Title', $event->newValue);
        self::assertTrue($event->actorId->equals($actorId));
        self::assertSame('New Title', $card->title());
    }

    public function testChangeTitleToSameReturnsNull(): void
    {
        $card = $this->createCard();

        self::assertNull($card->changeTitle('Title'));
    }

    public function testChangeDescriptionPreservesMarkdownTrailingSpaces(): void
    {
        $card = $this->createCard();

        $event = $card->changeDescription("line  \nnext");

        self::assertInstanceOf(CardFieldChanged::class, $event);
        self::assertSame("line  \nnext", $card->description());
        self::assertNull($card->changeDescription("line  \nnext"));
    }

    public function testChangeDueDateSetClearAndNoop(): void
    {
        $card = $this->createCard();
        $due = new \DateTimeImmutable('2026-12-01');

        $event = $card->changeDueDate($due);
        self::assertInstanceOf(CardFieldChanged::class, $event);
        self::assertSame('2026-12-01', $event->newValue);
        self::assertSame('2026-12-01', $card->dueDate()->format('Y-m-d'));

        self::assertNull($card->changeDueDate($due));

        $clear = $card->changeDueDate(null);
        self::assertInstanceOf(CardFieldChanged::class, $clear);
        self::assertNull($clear->newValue);
        self::assertNull($card->dueDate());
    }

    public function testMoveToEmitsEventWithOldAndNewPositions(): void
    {
        $card = $this->createCard();
        $newColumnId = Uuid::uuid4();

        $event = $card->moveTo($newColumnId, 2);

        self::assertInstanceOf(CardMoved::class, $event);
        self::assertSame(0, $event->oldPosition);
        self::assertSame(2, $event->newPosition);
        self::assertTrue($event->newColumnId->equals($newColumnId));
        self::assertSame(2, $card->position());
    }

    public function testMoveToSameColumnAndPositionReturnsNull(): void
    {
        $card = $this->createCard();

        self::assertNull($card->moveTo($card->columnId(), 0));
    }

    public function testArchiveAndRestoreAreIdempotent(): void
    {
        $card = $this->createCard();

        self::assertInstanceOf(CardArchived::class, $card->archive());
        self::assertTrue($card->isArchived());
        self::assertNull($card->archive());

        self::assertInstanceOf(CardRestored::class, $card->restore());
        self::assertFalse($card->isArchived());
        self::assertNull($card->restore());
    }

    public function testAddAndRemoveLabel(): void
    {
        $card = $this->createCard();
        $label = Label::create(Uuid::uuid4(), Uuid::uuid4(), 'Bug', '#ff0000');

        $added = $card->addLabel($label);
        self::assertInstanceOf(CardLabelAdded::class, $added);
        self::assertSame('Bug', $added->labelName);
        self::assertCount(1, $card->labels());
        self::assertNull($card->addLabel($label));

        $removed = $card->removeLabel($label);
        self::assertNotNull($removed);
        self::assertCount(0, $card->labels());
        self::assertNull($card->removeLabel($label));
    }

    public function testAssignAndUnassign(): void
    {
        $card = $this->createCard();
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');

        $assigned = $card->assign($user);
        self::assertInstanceOf(CardAssigned::class, $assigned);
        self::assertSame('Alice', $assigned->userName);
        self::assertCount(1, $card->assignees());
        self::assertNull($card->assign($user));

        self::assertNotNull($card->unassign($user));
        self::assertCount(0, $card->assignees());
        self::assertNull($card->unassign($user));
    }
}
