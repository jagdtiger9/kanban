<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetCardDueDateCommand;
use App\Board\Application\MessageHandler\SetCardDueDateCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardFieldChanged;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SetCardDueDateCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
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

        return new SetCardDueDateCommandHandler($guard, $this->cardRepository, $this->eventDispatcher);
    }

    public function testSetsDueDate(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static fn (CardFieldChanged $event): bool => $event->field === 'due_date'
                && $event->oldValue === null
                && $event->newValue === '2026-12-01',
        ))->willReturnArgument(0);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string) $card->id(), '2026-12-01', (string) $actorId));

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

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static fn (CardFieldChanged $event): bool => $event->field === 'due_date'
                && $event->oldValue === '2026-12-01'
                && $event->newValue === null,
        ))->willReturnArgument(0);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string) $card->id(), null, (string) $actorId));

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

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(CardFieldChanged::class))->willReturnArgument(0);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetCardDueDateCommand((string) $card->id(), '2026-12-01', (string) $actorId));

        self::assertSame('2026-12-01', $card->dueDate()?->format('Y-m-d'));
    }
}
