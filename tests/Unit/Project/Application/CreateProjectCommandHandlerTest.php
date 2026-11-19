<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Message\Command\CreateProjectCommand;
use App\Project\Application\MessageHandler\CreateProjectCommandHandler;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateProjectCommandHandlerTest extends TestCase
{
    public function testCreatesProjectBoardColumnsAndAdminMembership(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $boardRepository = $this->createMock(BoardRepository::class);
        $columnRepository = $this->createMock(ColumnRepository::class);
        $memberRepository = $this->createMock(ProjectMemberRepository::class);

        $savedProject = null;
        $projectRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (Project $project) use (&$savedProject): bool {
                $savedProject = $project;

                return true;
            }));

        $savedBoard = null;
        $boardRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (Board $board) use (&$savedBoard): bool {
                $savedBoard = $board;

                return true;
            }));

        $savedColumns = [];
        $columnRepository->expects($this->exactly(3))->method('save')
            ->with($this->callback(static function (Column $column) use (&$savedColumns): bool {
                $savedColumns[] = $column;

                return true;
            }));

        $savedMember = null;
        $memberRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (ProjectMember $member) use (&$savedMember): bool {
                $savedMember = $member;

                return true;
            }));

        $handler = new CreateProjectCommandHandler(
            $projectRepository,
            $boardRepository,
            $columnRepository,
            $memberRepository,
        );
        $actorId = Uuid::uuid4();

        $result = $handler->__invoke(new CreateProjectCommand('My Project', (string) $actorId));

        self::assertSame($savedProject, $result);
        self::assertSame('My Project', $result->name());
        self::assertTrue($savedBoard->projectId()->equals($result->id()));
        self::assertSame('My Project', $savedBoard->name());
        self::assertSame(
            ['To Do', 'In Progress', 'Done'],
            array_map(static fn(Column $column): string => $column->name(), $savedColumns),
        );
        self::assertSame(
            [0, 1, 2],
            array_map(static fn(Column $column): int => $column->position(), $savedColumns),
        );
        self::assertTrue($savedMember->projectId()->equals($result->id()));
        self::assertTrue($savedMember->userId()->equals($actorId));
        self::assertSame(MemberRole::Admin, $savedMember->role());
        self::assertTrue($savedMember->isFavourite());
    }
}
