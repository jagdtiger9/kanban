<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\RenameColumnCommand;
use App\Board\Application\MessageHandler\RenameColumnCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Event\ColumnRenamed;
use App\Board\Domain\Repository\BoardRepository;
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

final class RenameColumnCommandHandlerTest extends TestCase
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

    private function handler(?Column $column, ?Board $board, ?ProjectMember $actorMembership): RenameColumnCommandHandler
    {
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new RenameColumnCommandHandler(
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testRenamesAndSaves(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->columnRepository->expects($this->once())->method('save')->with($column);

        $result = $this->handler($column, $board, $member)
            ->__invoke(new RenameColumnCommand((string) $column->id(), 'Backlog', (string) $actorId));

        self::assertSame('Backlog', $result->name());

        $events = array_values(array_filter(
            $result->releaseEvents(),
            static fn (object $event): bool => $event instanceof ColumnRenamed,
        ));
        self::assertCount(1, $events);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($column->id()->toString(), $events[0]->columnId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testThrowsWhenColumnMissing(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        $this->handler(null, null, null)
            ->__invoke(new RenameColumnCommand((string) Uuid::uuid4(), 'X', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($column, $board, null)
            ->__invoke(new RenameColumnCommand((string) $column->id(), 'X', (string) Uuid::uuid4()));
    }
}
