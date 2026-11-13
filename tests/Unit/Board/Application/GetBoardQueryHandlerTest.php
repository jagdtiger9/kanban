<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Query\GetBoardQuery;
use App\Board\Application\MessageHandler\GetBoardQueryHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\LabelRepository;
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
use Ramsey\Uuid\UuidInterface;

final class GetBoardQueryHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private BoardRepository $boardRepository;
    private ColumnRepository $columnRepository;
    private CardRepository $cardRepository;
    private LabelRepository $labelRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->labelRepository = $this->createMock(LabelRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    /**
     * @param list<Column> $columns
     * @param array<string, list<Card>> $cardsByColumn
     * @param list<Label> $labels
     */
    private function handler(
        ?Project $project,
        ?Board $board,
        ?ProjectMember $actorMembership,
        array $columns,
        array $cardsByColumn,
        array $labels,
    ): GetBoardQueryHandler {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->boardRepository->method('findByProject')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->columnRepository->method('findByBoard')->willReturn($columns);
        $this->cardRepository->method('findByColumn')->willReturnCallback(
            static fn (UuidInterface $columnId): array => $cardsByColumn[(string) $columnId] ?? [],
        );
        $this->labelRepository->method('findByBoard')->willReturn($labels);

        return new GetBoardQueryHandler(
            $this->projectRepository,
            $this->boardRepository,
            $this->columnRepository,
            $this->cardRepository,
            $this->labelRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testReturnsBoardIdColumnsAndLabels(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);
        $columns = [
            Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0),
            Column::create(Uuid::uuid4(), $board->id(), 'Done', 1),
        ];
        $labels = [Label::create(Uuid::uuid4(), $board->id(), 'Bug', '#ff0000')];

        $result = $this->handler($project, $board, $admin, $columns, [], $labels)
            ->__invoke(new GetBoardQuery((string) $project->id(), (string) $actorId));

        self::assertTrue($result['boardId']->equals($board->id()));
        self::assertSame($columns, $result['columns']);
        self::assertSame([], $result['cards']);
        self::assertSame($labels, $result['labels']);
    }

    public function testExcludesArchivedCardsByDefault(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $first = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $second = Column::create(Uuid::uuid4(), $board->id(), 'Done', 1);

        $active = Card::create(Uuid::uuid4(), $first->id(), $actorId, 'Active', '', 0);
        $archived = Card::create(Uuid::uuid4(), $first->id(), $actorId, 'Archived', '', 1);
        $archived->archive();
        $activeSecond = Card::create(Uuid::uuid4(), $second->id(), $actorId, 'Active 2', '', 0);

        $result = $this->handler(
            $project,
            $board,
            $member,
            [$first, $second],
            [
                (string) $first->id() => [$active, $archived],
                (string) $second->id() => [$activeSecond],
            ],
            [],
        )->__invoke(new GetBoardQuery((string) $project->id(), (string) $actorId));

        self::assertSame([$active, $activeSecond], $result['cards']);
    }

    public function testIncludesArchivedCardsWhenFlagged(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $active = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'Active', '', 0);
        $archived = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'Archived', '', 1);
        $archived->archive();

        $result = $this->handler(
            $project,
            $board,
            $member,
            [$column],
            [(string) $column->id() => [$active, $archived]],
            [],
        )->__invoke(new GetBoardQuery((string) $project->id(), (string) $actorId, true));

        self::assertSame([$active, $archived], $result['cards']);
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null, null, [], [], [])
            ->__invoke(new GetBoardQuery((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, $board, null, [], [], [])
            ->__invoke(new GetBoardQuery((string) $project->id(), (string) Uuid::uuid4()));
    }
}
