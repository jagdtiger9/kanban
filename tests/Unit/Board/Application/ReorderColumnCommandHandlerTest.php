<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\ReorderColumnCommand;
use App\Board\Application\MessageHandler\ReorderColumnCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Event\ColumnReordered;
use App\Board\Domain\Repository\BoardRepository;
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

final class ReorderColumnCommandHandlerTest extends TestCase
{
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Column $column, ?Board $board, ?ProjectMember $actorMembership, array $boardColumns): ReorderColumnCommandHandler
    {
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->columnRepository->method('findByBoard')->willReturn($boardColumns);

        return new ReorderColumnCommandHandler(
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
            new ColumnReorderService(),
        );
    }

    public function testReordersAndSavesAllColumns(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $a = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $b = Column::create(Uuid::uuid4(), $board->id(), 'In Progress', 1);
        $c = Column::create(Uuid::uuid4(), $board->id(), 'Done', 2);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $this->columnRepository->expects($this->exactly(3))->method('save');

        $result = $this->handler($c, $board, $member, [$a, $b, $c])
            ->__invoke(new ReorderColumnCommand((string) $c->id(), 0, (string) $actorId));

        self::assertSame($c, $result);
        self::assertSame(0, $c->position());
        self::assertSame(1, $a->position());
        self::assertSame(2, $b->position());

        $events = array_values(array_filter(
            $c->releaseEvents(),
            static fn(object $event): bool => $event instanceof ColumnReordered,
        ));
        self::assertCount(1, $events);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($c->id()->toString(), $events[0]->columnId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($column, $board, null, [$column])
            ->__invoke(new ReorderColumnCommand((string) $column->id(), 0, (string) Uuid::uuid4()));
    }

    public function testThrowsWhenColumnMissing(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        $this->handler(null, null, null, [])
            ->__invoke(new ReorderColumnCommand((string) Uuid::uuid4(), 0, (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsPlainMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->columnRepository->expects($this->never())->method('save');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($column, $board, $member, [$column])
            ->__invoke(new ReorderColumnCommand((string) $column->id(), 0, (string) $actorId));
    }
}
