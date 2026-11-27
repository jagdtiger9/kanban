<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\AddChecklistItemCommand;
use App\Board\Application\MessageHandler\AddChecklistItemCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardChecklistChanged;
use App\Board\Domain\Exception\CardNotFoundException;
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

final class AddChecklistItemCommandHandlerTest extends TestCase
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

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $actorMembership): AddChecklistItemCommandHandler
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

        return new AddChecklistItemCommandHandler($guard, $this->cardRepository);
    }

    public function testMemberAddsItem(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $item = $this->handler($card, $column, $board, $member)
            ->__invoke(new AddChecklistItemCommand((string)$card->id(), '  Write tests  ', (string)$actorId));

        self::assertSame('Write tests', $item['text']);
        self::assertFalse($item['done']);
        self::assertSame(
            [['id' => $item['id'], 'text' => 'Write tests', 'done' => false]],
            $card->checklist(),
        );

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardChecklistChanged::class, $events[0]);
        self::assertSame('added', $events[0]->action);
        self::assertSame($item['id'], $events[0]->itemId);
        self::assertSame('Write tests', $events[0]->itemText);
        self::assertSame((string)$actorId, $events[0]->actorId);
    }

    public function testOutsiderIsForbidden(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new AddChecklistItemCommand((string)$card->id(), 'Write tests', (string)$actorId));
    }

    public function testCardNotFound(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new AddChecklistItemCommand((string)Uuid::uuid4(), 'Write tests', (string)Uuid::uuid4()));
    }
}
