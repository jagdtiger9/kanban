<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\DeleteColumnCommand;
use App\Board\Application\MessageHandler\DeleteColumnCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotEmptyException;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Event\ColumnDeleted;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\ColumnReorderService;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeleteColumnCommandHandlerTest extends TestCase
{
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private CardRepository $cardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        int $cardCount,
        array $boardColumns,
    ): DeleteColumnCommandHandler {
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->cardRepository->method('countByColumn')->willReturn($cardCount);
        $this->columnRepository->method('findByBoard')->willReturn($boardColumns);

        return new DeleteColumnCommandHandler(
            $this->columnRepository,
            $this->boardRepository,
            $this->cardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
            new ColumnReorderService(),
        );
    }

    public function testRemovesEmptyColumnAndRenumbersSurvivors(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $a = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $b = Column::create(Uuid::uuid4(), $board->id(), 'In Progress', 1);
        $c = Column::create(Uuid::uuid4(), $board->id(), 'Done', 2);
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $this->columnRepository->expects($this->once())->method('remove')->with($b);
        $this->columnRepository->expects($this->exactly(2))->method('save');

        $this->handler($b, $board, $admin, 0, [$a, $b, $c])
            ->__invoke(new DeleteColumnCommand((string) $b->id(), (string) $actorId));

        $events = array_values(array_filter(
            $b->releaseEvents(),
            static fn (object $event): bool => $event instanceof ColumnDeleted,
        ));
        self::assertCount(1, $events);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($b->id()->toString(), $events[0]->columnId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertSame(0, $a->position());
        self::assertSame(1, $c->position());
    }

    public function testThrowsWhenColumnHasCards(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $this->columnRepository->expects($this->never())->method('remove');
        $this->expectException(ColumnNotEmptyException::class);

        $this->handler($column, $board, $admin, 3, [$column])
            ->__invoke(new DeleteColumnCommand((string) $column->id(), (string) $actorId));
    }

    public function testThrowsWhenActorIsPlainMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->columnRepository->expects($this->never())->method('remove');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($column, $board, $member, 0, [$column])
            ->__invoke(new DeleteColumnCommand((string) $column->id(), (string) $actorId));
    }

    public function testThrowsWhenColumnMissing(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        $this->handler(null, null, null, 0, [])
            ->__invoke(new DeleteColumnCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }
}
