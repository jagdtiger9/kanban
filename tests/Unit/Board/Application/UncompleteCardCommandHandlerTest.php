<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\UncompleteCardCommand;
use App\Board\Application\MessageHandler\UncompleteCardCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardOccurrence;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Event\CardOccurrenceUncompleted;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardOccurrenceRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\OccurrenceCalculator;
use App\Board\Domain\ValueObject\RecurrenceRule;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UncompleteCardCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CardOccurrenceRepository $occurrenceRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->occurrenceRepository = $this->createMock(CardOccurrenceRepository::class);
    }

    private function handler(Card $card, Column $column, Board $board, ProjectMember $actorMembership): UncompleteCardCommandHandler
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

        return new UncompleteCardCommandHandler(
            $guard,
            $this->occurrenceRepository,
            new OccurrenceCalculator(),
            $this->cardRepository,
        );
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

    private function currentOccurrence(Card $card, UuidInterface $completedBy): CardOccurrence
    {
        $occurrence = CardOccurrence::create(
            Uuid::uuid4(),
            $card->id(),
            new \DateTimeImmutable(),
            $completedBy,
        );
        $occurrence->releaseEvents();

        return $occurrence;
    }

    public function testUncompletesCurrentPeriod(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]));
        $card->releaseEvents();
        $occurrence = $this->currentOccurrence($card, $actorId);

        $this->occurrenceRepository->method('findByCardAndPeriod')->willReturn($occurrence);
        $this->occurrenceRepository->expects($this->once())->method('remove')->with($occurrence);

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );

        $events = array_values(
            array_filter(
                $occurrence->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardOccurrenceUncompleted,
            )
        );
        self::assertCount(1, $events);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testUncompleteIsIdempotentWithoutCurrentPeriodRow(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]));
        $card->releaseEvents();

        $this->occurrenceRepository->method('findByCardAndPeriod')->willReturn(null);
        $this->occurrenceRepository->expects($this->never())->method('remove');

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );
    }

    public function testUncompleteRejectsCardWithoutRecurrence(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();

        $this->occurrenceRepository->expects($this->never())->method('remove');

        $this->expectException(InvalidRecurrenceException::class);

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );
    }

    public function testNonExecutorCannotUncomplete(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]));
        $card->assignExecutor(User::create(Uuid::uuid4(), 'other@example.com', 'hash', 'Other'));
        $card->releaseEvents();

        $this->occurrenceRepository->expects($this->never())->method('remove');

        $this->expectException(CardForbiddenException::class);

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );
    }

    public function testExecutorCanUncomplete(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context();
        $card->changeRecurrence(RecurrenceRule::weekly([1, 2, 3, 4, 5, 6, 7]));
        $card->assignExecutor(User::create($actorId, 'executor@example.com', 'hash', 'Executor'));
        $card->releaseEvents();
        $occurrence = $this->currentOccurrence($card, $actorId);

        $this->occurrenceRepository->method('findByCardAndPeriod')->willReturn($occurrence);
        $this->occurrenceRepository->expects($this->once())->method('remove');

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );
    }

    public function testUncompletesStickerCardWithoutOccurrenceRepository(): void
    {
        [$card, $column, $board, $member, $actorId] = $this->context(ColumnKind::Sticker);
        $card->complete(Uuid::uuid4());
        $card->releaseEvents();

        $this->occurrenceRepository->expects($this->never())->method('remove');
        $this->cardRepository->expects($this->once())->method('save')->with($card);

        $this->handler($card, $column, $board, $member)->__invoke(
            new UncompleteCardCommand((string)$card->id(), (string)$actorId),
        );

        self::assertNull($card->completedAt());
        self::assertNull($card->completedBy());
    }
}
