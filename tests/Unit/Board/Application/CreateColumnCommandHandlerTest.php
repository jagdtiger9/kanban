<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\CreateColumnCommand;
use App\Board\Application\MessageHandler\CreateColumnCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\ColumnCreated;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateColumnCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private BoardRepository $boardRepository;
    private ColumnRepository $columnRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Project $project, ?Board $board, ?ProjectMember $actorMembership, array $existingColumns): CreateColumnCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->boardRepository->method('findByProject')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->columnRepository->method('findByBoard')->willReturn($existingColumns);

        return new CreateColumnCommandHandler(
            $this->projectRepository,
            $this->boardRepository,
            $this->columnRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testAppendsColumnAtEnd(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);
        $existing = [
            Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0),
            Column::create(Uuid::uuid4(), $board->id(), 'In Progress', 1),
        ];

        $captured = null;
        $this->columnRepository->expects($this->once())->method('save')->with($this->callback(
            static function (Column $column) use (&$captured): bool {
                $captured = $column;

                return true;
            },
        ));

        $result = $this->handler($project, $board, $admin, $existing)
            ->__invoke(new CreateColumnCommand((string) $project->id(), 'Review', (string) $actorId));

        $events = $result->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ColumnCreated::class, $events[0]);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($result->id()->toString(), $events[0]->columnId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertSame($captured, $result);
        self::assertSame('Review', $result->name());
        self::assertSame(2, $result->position());
        self::assertTrue($result->boardId()->equals($board->id()));
    }

    public function testFirstColumnGetsPositionZero(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $this->columnRepository->expects($this->once())->method('save');

        $result = $this->handler($project, $board, $admin, [])
            ->__invoke(new CreateColumnCommand((string) $project->id(), 'To Do', (string) $actorId));

        self::assertSame(0, $result->position());
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null, null, [])
            ->__invoke(new CreateColumnCommand((string) Uuid::uuid4(), 'X', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, $board, null, [])
            ->__invoke(new CreateColumnCommand((string) $project->id(), 'X', (string) Uuid::uuid4()));
    }
}
