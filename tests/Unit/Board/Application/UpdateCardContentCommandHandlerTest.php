<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\UpdateCardContentCommand;
use App\Board\Application\MessageHandler\UpdateCardContentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Exception\CardForbiddenException;
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
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UpdateCardContentCommandHandlerTest extends TestCase
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
    ): UpdateCardContentCommandHandler {
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

        return new UpdateCardContentCommandHandler($guard, $this->cardRepository);
    }

    private function cardAndContext(UuidInterface $creatorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), $creatorId, 'Old', 'old desc', 0);
        $card->releaseEvents();

        return [$card, $column, $board, $project];
    }

    public function testCreatorUpdatesTitleAndDescription(): void
    {
        [$card, $column, $board, $project] = $this->cardAndContext($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->cardRepository->expects($this->once())->method('save');

        $result = $this->handler($card, $column, $board, $member)
            ->__invoke(new UpdateCardContentCommand((string) $card->id(), 'New', 'new desc', (string) $actorId));

        self::assertSame($card, $result);
        self::assertSame('New', $result->title());
        self::assertSame('new desc', $result->description());
        self::assertSame(
            ['title', 'description'],
            array_map(static fn (CardFieldChanged $event): string => $event->field, $result->releaseEvents()),
        );
    }

    public function testThrowsWhenActorIsNotCreator(): void
    {
        [$card, $column, $board, $project] = $this->cardAndContext(Uuid::uuid4());
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(CardForbiddenException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new UpdateCardContentCommand((string) $card->id(), 'New', 'new desc', (string) $otherId));
    }

    public function testThrowsWhenActorIsNotMemberEvenIfCreator(): void
    {
        [$card, $column, $board] = $this->cardAndContext($actorId = Uuid::uuid4());

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new UpdateCardContentCommand((string) $card->id(), 'New', 'new desc', (string) $actorId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new UpdateCardContentCommand((string) Uuid::uuid4(), 'New', '', (string) Uuid::uuid4()));
    }

    public function testProjectAdminCanEditOtherMembersCard(): void
    {
        [$card, $column, $board, $project] = $this->cardAndContext(Uuid::uuid4());
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->cardRepository->expects($this->once())->method('save');

        $result = $this->handler($card, $column, $board, $admin)
            ->__invoke(new UpdateCardContentCommand((string) $card->id(), 'New', 'new desc', (string) $adminId));

        self::assertSame(
            ['title', 'description'],
            array_map(static fn (CardFieldChanged $event): string => $event->field, $result->releaseEvents()),
        );
        self::assertSame('New', $result->title());
        self::assertSame('new desc', $result->description());
    }

    public function testGlobalAdminCanEditCardWithoutMembership(): void
    {
        [$card, $column, $board] = $this->cardAndContext(Uuid::uuid4());
        $adminUser = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $adminUser->promoteToGlobalAdmin();

        $this->cardRepository->expects($this->once())->method('save');

        $result = $this->handler($card, $column, $board, null, $adminUser)
            ->__invoke(new UpdateCardContentCommand((string) $card->id(), 'New', 'new desc', (string) $adminUser->id()));

        self::assertSame(
            ['title', 'description'],
            array_map(static fn (CardFieldChanged $event): string => $event->field, $result->releaseEvents()),
        );
        self::assertSame('New', $result->title());
    }
}
