<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\RecurrenceRulePayload;
use App\Board\Application\Message\Command\SetCardRecurrenceCommand;
use App\Board\Application\MessageHandler\SetCardRecurrenceCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Event\CardRecurrenceChanged;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\ValueObject\RecurrenceRule;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SetCardRecurrenceCommandHandlerTest extends TestCase
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

    private function handler(Card $card, Column $column, Board $board, ProjectMember $actorMembership): SetCardRecurrenceCommandHandler
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

        return new SetCardRecurrenceCommandHandler($guard, $this->cardRepository);
    }

    /**
     * @return array{Card, Column, Board, ProjectMember, UuidInterface}
     */
    private function context(ColumnKind $kind = ColumnKind::Task): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0, $kind);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        return [$card, $column, $board, $member, $actorId];
    }

    private function releasedRecurrenceChange(Card $card): CardRecurrenceChanged
    {
        $events = array_values(
            array_filter(
                $card->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardRecurrenceChanged,
            )
        );
        self::assertCount(1, $events);

        return $events[0];
    }

    public function testSetsWeeklyRule(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)->__invoke(
            new SetCardRecurrenceCommand(
                (string)$card->id(),
                new RecurrenceRulePayload('weekly', weekdays: [3, 1, 3]),
                (string)$actorId,
            )
        );

        $rule = $card->recurrence();
        self::assertNotNull($rule);
        self::assertTrue(RecurrenceRule::weekly([1, 3])->equals($rule));

        $event = $this->releasedRecurrenceChange($card);
        self::assertNull($event->oldKind);
        self::assertSame('weekly', $event->newKind);
        self::assertSame($actorId->toString(), $event->actorId);
    }

    public function testClearsRecurrence(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]));
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)->__invoke(
            new SetCardRecurrenceCommand(
                (string)$card->id(),
                null,
                (string)$actorId,
            )
        );

        self::assertNull($card->recurrence());
        self::assertSame('weekly', $this->releasedRecurrenceChange($card)->oldKind);
    }

    public function testInvalidRuleIsRejected(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();

        $this->cardRepository->expects($this->never())->method('save');

        $this->expectException(InvalidRecurrenceException::class);

        $this->handler($card, $column, $board, $member)->__invoke(
            new SetCardRecurrenceCommand(
                (string)$card->id(),
                new RecurrenceRulePayload('weekly', weekdays: []),
                (string)$actorId,
            )
        );
    }

    public function testRejectsStickerCard(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context(ColumnKind::Sticker);

        $this->cardRepository->expects($this->never())->method('save');

        $this->expectException(CardKindMismatchException::class);

        $this->handler($card, $column, $board, $member)->__invoke(
            new SetCardRecurrenceCommand(
                (string)$card->id(),
                null,
                (string)$actorId,
            )
        );
    }
}
