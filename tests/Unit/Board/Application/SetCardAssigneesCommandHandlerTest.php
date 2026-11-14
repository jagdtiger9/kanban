<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetCardAssigneesCommand;
use App\Board\Application\MessageHandler\SetCardAssigneesCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SetCardAssigneesCommandHandlerTest extends TestCase
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

    private function handler(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        array $projectMembers,
        array $usersById,
    ): SetCardAssigneesCommandHandler {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->memberRepository->method('findByProject')->willReturn($projectMembers);
        $this->userRepository->method('findById')->willReturnCallback(
            static fn (UuidInterface $id): ?User => $usersById[(string) $id] ?? null,
        );

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new SetCardAssigneesCommandHandler(
            $guard,
            $this->memberRepository,
            $this->userRepository,
            $this->cardRepository,
        );
    }

    public function testReplacesAssigneeSet(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $user1 = User::create(Uuid::uuid4(), 'one@example.com', 'hash', 'One');
        $user2 = User::create(Uuid::uuid4(), 'two@example.com', 'hash', 'Two');
        $card->assign($user1);
        $members = [
            ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member),
            ProjectMember::create(Uuid::uuid4(), $project->id(), $user2->id(), MemberRole::Member),
        ];
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member, $members, [(string) $user2->id() => $user2])
            ->__invoke(new SetCardAssigneesCommand((string) $card->id(), [(string) $user2->id()], (string) $actorId));

        $events = $card->releaseEvents();
        self::assertCount(2, $events);
        $unassigned = $events[0];
        self::assertInstanceOf(CardUnassigned::class, $unassigned);
        self::assertSame($user1->id()->toString(), $unassigned->userId);
        $assigned = $events[1];
        self::assertInstanceOf(CardAssigned::class, $assigned);
        self::assertSame($user2->id()->toString(), $assigned->userId);
        self::assertFalse($card->assignees()->contains($user1));
        self::assertTrue($card->assignees()->contains($user2));
    }

    public function testNonCreatorMemberCanAssign(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $user = User::create(Uuid::uuid4(), 'one@example.com', 'hash', 'One');
        $members = [
            ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member),
            ProjectMember::create(Uuid::uuid4(), $project->id(), $user->id(), MemberRole::Member),
        ];
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member, $members, [(string) $user->id() => $user])
            ->__invoke(new SetCardAssigneesCommand((string) $card->id(), [(string) $user->id()], (string) $actorId));

        $events = $card->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardAssigned::class, $events[0]);
        self::assertTrue($card->assignees()->contains($user));
    }

    public function testThrowsWhenUserIsNotProjectMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $outsider = Uuid::uuid4();

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(ProjectMemberNotFoundException::class);

        $this->handler($card, $column, $board, $member, [$member], [])
            ->__invoke(new SetCardAssigneesCommand((string) $card->id(), [(string) $outsider], (string) $actorId));
    }
}
