<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetChecklistItemDoneCommand;
use App\Board\Application\MessageHandler\SetChecklistItemDoneCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardChecklistChanged;
use App\Board\Domain\Exception\ChecklistItemNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SetChecklistItemDoneCommandHandlerTest extends TestCase
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

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $actorMembership): SetChecklistItemDoneCommandHandler
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

        return new SetChecklistItemDoneCommandHandler($guard, $this->cardRepository);
    }

    public function testMemberTogglesItem(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $itemId = Uuid::uuid4();
        $card->addChecklistItem($itemId, 'Task');
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetChecklistItemDoneCommand((string)$card->id(), (string)$itemId, true, (string)$actorId));

        self::assertTrue($card->checklist()[0]['done']);

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardChecklistChanged::class, $events[0]);
        self::assertSame('toggled', $events[0]->action);
        self::assertSame((string)$itemId, $events[0]->itemId);
        self::assertTrue($events[0]->done);
        self::assertSame((string)$actorId, $events[0]->actorId);
    }

    public function testUnknownItemThrows(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(ChecklistItemNotFoundException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new SetChecklistItemDoneCommand((string)$card->id(), (string)Uuid::uuid4(), true, (string)$actorId));
    }

    public function testOutsiderIsForbidden(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $itemId = Uuid::uuid4();
        $card->addChecklistItem($itemId, 'Task');

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new SetChecklistItemDoneCommand((string)$card->id(), (string)$itemId, true, (string)$actorId));
    }
}
