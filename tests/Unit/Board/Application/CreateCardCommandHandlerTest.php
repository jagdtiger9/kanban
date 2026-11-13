<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\CreateCardCommand;
use App\Board\Application\MessageHandler\CreateCardCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateCardCommandHandlerTest extends TestCase
{
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private CardRepository $cardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function handler(?Column $column, ?Board $board, ?ProjectMember $actorMembership, array $existingCards): CreateCardCommandHandler
    {
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->cardRepository->method('findByColumn')->willReturn($existingCards);

        return new CreateCardCommandHandler(
            $this->columnRepository,
            $this->boardRepository,
            $this->cardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
            $this->eventDispatcher,
        );
    }

    public function testAppendsCardAtEndOfColumn(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $existing = [
            Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0),
            Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'B', '', 1),
        ];

        $captured = null;
        $this->cardRepository->expects($this->once())->method('save')->with($this->callback(
            static function (Card $card) use (&$captured): bool {
                $captured = $card;

                return true;
            },
        ));
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static function (CardCreated $event) use ($actorId): bool {
                return $event->actorId->equals($actorId);
            },
        ))->willReturnArgument(0);

        $result = $this->handler($column, $board, $member, $existing)
            ->__invoke(new CreateCardCommand((string) $column->id(), 'C', 'desc', (string) $actorId));

        self::assertSame($captured, $result);
        self::assertSame('C', $result->title());
        self::assertSame('desc', $result->description());
        self::assertSame(2, $result->position());
        self::assertTrue($result->columnId()->equals($column->id()));
        self::assertTrue($result->creatorId()->equals($actorId));
    }

    public function testFirstCardGetsPositionZero(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(CardCreated::class))->willReturnArgument(0);

        $result = $this->handler($column, $board, $member, [])
            ->__invoke(new CreateCardCommand((string) $column->id(), 'A', '', (string) $actorId));

        self::assertSame(0, $result->position());
    }

    public function testThrowsWhenColumnMissing(): void
    {
        $this->expectException(ColumnNotFoundException::class);

        $this->handler(null, null, null, [])
            ->__invoke(new CreateCardCommand((string) Uuid::uuid4(), 'A', '', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($column, $board, null, [])
            ->__invoke(new CreateCardCommand((string) $column->id(), 'A', '', (string) Uuid::uuid4()));
    }
}
