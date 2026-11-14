<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\ArchiveCardCommand;
use App\Board\Application\MessageHandler\ArchiveCardCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ArchiveCardCommandHandlerTest extends TestCase
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
        ?User $actorUser = null,
    ): ArchiveCardCommandHandler {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->userRepository->method('findById')->willReturn($actorUser);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new ArchiveCardCommandHandler($guard, $this->cardRepository);
    }

    private function releasedArchiveEvent(Card $card): CardArchived
    {
        $events = array_values(array_filter(
            $card->releaseEvents(),
            static fn (object $event): bool => $event instanceof CardArchived,
        ));
        self::assertCount(1, $events);

        return $events[0];
    }

    public function testCreatorArchivesCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new ArchiveCardCommand((string) $card->id(), (string) $actorId));

        self::assertTrue($card->isArchived());
        self::assertSame($actorId->toString(), $this->releasedArchiveEvent($card)->actorId);
    }

    public function testThrowsWhenActorIsNotCreator(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(CardForbiddenException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new ArchiveCardCommand((string) $card->id(), (string) $otherId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new ArchiveCardCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testProjectAdminCanArchiveOtherMembersCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $admin)
            ->__invoke(new ArchiveCardCommand((string) $card->id(), (string) $adminId));

        self::assertTrue($card->isArchived());
        self::assertSame($adminId->toString(), $this->releasedArchiveEvent($card)->actorId);
    }

    public function testGlobalAdminCanArchiveCardWithoutMembership(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $adminUser = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $adminUser->promoteToGlobalAdmin();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, null, $adminUser)
            ->__invoke(new ArchiveCardCommand((string) $card->id(), (string) $adminUser->id()));

        self::assertTrue($card->isArchived());
        self::assertSame($adminUser->id()->toString(), $this->releasedArchiveEvent($card)->actorId);
    }
}
