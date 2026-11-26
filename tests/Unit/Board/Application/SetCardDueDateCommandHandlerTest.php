<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetCardDueDateCommand;
use App\Board\Application\MessageHandler\SetCardDueDateCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SetCardDueDateCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $actorMembership): SetCardDueDateCommandHandler
    {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new SetCardDueDateCommandHandler($guard, $this->cardRepository);
    }

    private function releasedFieldChange(Card $card): CardFieldChanged
    {
        $events = array_values(
            array_filter(
                $card->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardFieldChanged,
            )
        );
        self::assertCount(1, $events);

        return $events[0];
    }

    public function testSetsDueDate(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string)$card->id(), '2026-12-01', (string)$actorId));

        $event = $this->releasedFieldChange($card);
        self::assertSame('due_date', $event->field);
        self::assertNull($event->oldValue);
        self::assertSame('2026-12-01', $event->newValue);
        self::assertSame('2026-12-01', $card->dueDate()?->format('Y-m-d'));
    }

    public function testClearsDueDate(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0, new \DateTimeImmutable('2026-12-01'));
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string)$card->id(), null, (string)$actorId));

        $event = $this->releasedFieldChange($card);
        self::assertSame('due_date', $event->field);
        self::assertSame('2026-12-01', $event->oldValue);
        self::assertNull($event->newValue);
        self::assertNull($card->dueDate());
    }

    public function testNonCreatorMemberCanSetDueDate(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string)$card->id(), '2026-12-01', (string)$actorId));

        self::assertSame('due_date', $this->releasedFieldChange($card)->field);
        self::assertSame('2026-12-01', $card->dueDate()?->format('Y-m-d'));
    }

    public function testRejectsStickerCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'Stickers', 0, ColumnKind::Sticker);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->never())->method('save');

        $this->expectException(CardKindMismatchException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string)$card->id(), '2026-12-01', (string)$actorId));
    }
}
