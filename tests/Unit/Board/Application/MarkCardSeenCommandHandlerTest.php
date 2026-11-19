<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\MarkCardSeenCommand;
use App\Board\Application\MessageHandler\MarkCardSeenCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\CardViewRepository;
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

final class MarkCardSeenCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CardViewRepository $cardViewRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->cardViewRepository = $this->createMock(CardViewRepository::class);
    }

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $member): MarkCardSeenCommandHandler
    {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($member);

        return new MarkCardSeenCommandHandler(
            new CardGuard(
                $this->cardRepository,
                $this->columnRepository,
                $this->boardRepository,
                new MembershipGuard($this->memberRepository, $this->userRepository),
            ),
            $this->cardViewRepository,
        );
    }

    public function testCreatesViewOnFirstOpen(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->cardViewRepository->method('findByUserAndCard')->willReturn(null);
        $saved = null;
        $this->cardViewRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (CardView $view) use (&$saved, $actorId, $card): bool {
                $saved = $view;

                return $view->userId()->equals($actorId) && $view->cardId()->equals($card->id());
            }));

        $this->handler($card, $column, $board, $member)
            ->__invoke(new MarkCardSeenCommand((string) $card->id(), (string) $actorId));

        self::assertInstanceOf(CardView::class, $saved);
    }

    public function testAdvancesExistingView(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $view = CardView::create(Uuid::uuid4(), $actorId, $card->id());
        $this->cardViewRepository->method('findByUserAndCard')->willReturn($view);
        $this->cardViewRepository->expects($this->once())->method('save')->with($view);

        $before = new \DateTimeImmutable();
        $this->handler($card, $column, $board, $member)
            ->__invoke(new MarkCardSeenCommand((string) $card->id(), (string) $actorId));

        self::assertGreaterThanOrEqual($before, $view->lastSeenAt());
    }

    public function testRejectsNonMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->cardViewRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new MarkCardSeenCommand((string) $card->id(), (string) $actorId));
    }
}
