<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetCardLinkedCardCommand;
use App\Board\Application\MessageHandler\SetCardLinkedCardCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Exception\CardLinkException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SetCardLinkedCardCommandHandlerTest extends TestCase
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

    /**
     * @param array<string, Card> $cards
     * @param array<string, Column> $columns
     * @param array<string, Board> $boards
     * @param array<string, ProjectMember> $members
     */
    private function handler(array $cards, array $columns, array $boards, array $members): SetCardLinkedCardCommandHandler
    {
        $this->cardRepository->method('findById')->willReturnCallback(
            static fn(UuidInterface $id): ?Card => $cards[$id->toString()] ?? null,
        );
        $this->columnRepository->method('findById')->willReturnCallback(
            static fn(UuidInterface $id): ?Column => $columns[$id->toString()] ?? null,
        );
        $this->boardRepository->method('findById')->willReturnCallback(
            static fn(UuidInterface $id): ?Board => $boards[$id->toString()] ?? null,
        );
        $this->memberRepository->method('findByProjectAndUser')->willReturnCallback(
            static fn(UuidInterface $projectId, UuidInterface $userId): ?ProjectMember => $members[$projectId->toString()] ?? null,
        );

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new SetCardLinkedCardCommandHandler($guard, $this->cardRepository);
    }

    private function releasedFieldChange(Card $card): CardFieldChanged
    {
        $events = array_values(
            array_filter(
                $card->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardFieldChanged,
            )
        );
        self::assertCount(1, $events);

        return $events[0];
    }

    /**
     * @return array{Project, Board, Column, ProjectMember}
     */
    private function projectGraph(UuidInterface $actorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        return [$project, $board, $column, $member];
    }

    public function testLinksCardInSameProject(): void
    {
        $actorId = Uuid::uuid4();
        [$project, $board, $column, $member] = $this->projectGraph($actorId);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $linked = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'B', '', 1);
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save')->with($card);

        $this->handler(
            [$card->id()->toString() => $card, $linked->id()->toString() => $linked],
            [$column->id()->toString() => $column],
            [$board->id()->toString() => $board],
            [$project->id()->toString() => $member],
        )->__invoke(new SetCardLinkedCardCommand((string)$card->id(), (string)$linked->id(), (string)$actorId));

        self::assertTrue($card->linkedCardId()?->equals($linked->id()));

        $event = $this->releasedFieldChange($card);
        self::assertSame('linked_card', $event->field);
        self::assertNull($event->oldValue);
        self::assertSame((string)$linked->id(), $event->newValue);
        self::assertSame((string)$actorId, $event->actorId);
    }

    public function testUnlinksCard(): void
    {
        $actorId = Uuid::uuid4();
        [$project, $board, $column, $member] = $this->projectGraph($actorId);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $linkedId = Uuid::uuid4();
        $card->changeLinkedCard($linkedId);
        $card->releaseEvents();

        $this->cardRepository->expects($this->once())->method('save')->with($card);

        $this->handler(
            [$card->id()->toString() => $card],
            [$column->id()->toString() => $column],
            [$board->id()->toString() => $board],
            [$project->id()->toString() => $member],
        )->__invoke(new SetCardLinkedCardCommand((string)$card->id(), null, (string)$actorId));

        self::assertNull($card->linkedCardId());

        $event = $this->releasedFieldChange($card);
        self::assertSame('linked_card', $event->field);
        self::assertSame($linkedId->toString(), $event->oldValue);
        self::assertNull($event->newValue);
    }

    public function testRejectsCardFromAnotherProject(): void
    {
        $actorId = Uuid::uuid4();
        [$projectA, $boardA, $columnA, $memberA] = $this->projectGraph($actorId);
        [$projectB, $boardB, $columnB, $memberB] = $this->projectGraph($actorId);
        $card = Card::create(Uuid::uuid4(), $columnA->id(), Uuid::uuid4(), 'A', '', 0);
        $other = Card::create(Uuid::uuid4(), $columnB->id(), Uuid::uuid4(), 'B', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->never())->method('save');

        $this->expectException(CardLinkException::class);

        try {
            $this->handler(
                [$card->id()->toString() => $card, $other->id()->toString() => $other],
                [$columnA->id()->toString() => $columnA, $columnB->id()->toString() => $columnB],
                [$boardA->id()->toString() => $boardA, $boardB->id()->toString() => $boardB],
                [$projectA->id()->toString() => $memberA, $projectB->id()->toString() => $memberB],
            )->__invoke(new SetCardLinkedCardCommand((string)$card->id(), (string)$other->id(), (string)$actorId));
        } finally {
            self::assertNull($card->linkedCardId());
        }
    }

    public function testRejectsMissingLinkedCard(): void
    {
        $actorId = Uuid::uuid4();
        [$project, $board, $column, $member] = $this->projectGraph($actorId);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $card->releaseEvents();

        $this->cardRepository->expects($this->never())->method('save');

        $this->expectException(CardNotFoundException::class);

        $this->handler(
            [$card->id()->toString() => $card],
            [$column->id()->toString() => $column],
            [$board->id()->toString() => $board],
            [$project->id()->toString() => $member],
        )->__invoke(new SetCardLinkedCardCommand((string)$card->id(), (string)Uuid::uuid4(), (string)$actorId));
    }
}
