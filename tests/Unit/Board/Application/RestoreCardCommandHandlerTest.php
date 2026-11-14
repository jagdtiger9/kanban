<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\RestoreCardCommand;
use App\Board\Application\MessageHandler\RestoreCardCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Exception\CardForbiddenException;
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

final class RestoreCardCommandHandlerTest extends TestCase
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
    ): RestoreCardCommandHandler {
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

        return new RestoreCardCommandHandler($guard, $this->cardRepository);
    }

    private function releasedRestoreEvent(Card $card): CardRestored
    {
        $events = array_values(array_filter(
            $card->releaseEvents(),
            static fn (object $event): bool => $event instanceof CardRestored,
        ));
        self::assertCount(1, $events);

        return $events[0];
    }

    public function testCreatorRestoresArchivedCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $card->archive();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $member)
            ->__invoke(new RestoreCardCommand((string) $card->id(), (string) $actorId));

        self::assertFalse($card->isArchived());
        self::assertSame($actorId->toString(), $this->releasedRestoreEvent($card)->actorId);
    }

    public function testThrowsWhenActorIsNotCreator(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->archive();
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(CardForbiddenException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new RestoreCardCommand((string) $card->id(), (string) $otherId));
    }

    public function testProjectAdminCanRestoreOtherMembersCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->archive();
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, $admin)
            ->__invoke(new RestoreCardCommand((string) $card->id(), (string) $adminId));

        self::assertFalse($card->isArchived());
        self::assertSame($adminId->toString(), $this->releasedRestoreEvent($card)->actorId);
    }

    public function testGlobalAdminCanRestoreCardWithoutMembership(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->archive();
        $adminUser = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $adminUser->promoteToGlobalAdmin();

        $this->cardRepository->expects($this->once())->method('save');

        $this->handler($card, $column, $board, null, $adminUser)
            ->__invoke(new RestoreCardCommand((string) $card->id(), (string) $adminUser->id()));

        self::assertFalse($card->isArchived());
        self::assertSame($adminUser->id()->toString(), $this->releasedRestoreEvent($card)->actorId);
    }
}
