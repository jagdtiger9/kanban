<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\DeleteLabelCommand;
use App\Board\Application\MessageHandler\DeleteLabelCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Board\Domain\Event\LabelDeleted;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeleteLabelCommandHandlerTest extends TestCase
{
    private LabelRepository $labelRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->labelRepository = $this->createMock(LabelRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Label $label, ?Board $board, ?ProjectMember $actorMembership): DeleteLabelCommandHandler
    {
        $this->labelRepository->method('findById')->willReturn($label);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new DeleteLabelCommandHandler(
            $this->labelRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testAdminRemovesLabel(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $label = Label::create(Uuid::uuid4(), $board->id(), 'Old', '#000000');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $this->labelRepository->expects($this->once())->method('remove')->with($label);

        $this->handler($label, $board, $admin)
            ->__invoke(new DeleteLabelCommand((string) $label->id(), (string) $actorId));

        $events = array_values(array_filter(
            $label->releaseEvents(),
            static fn (object $event): bool => $event instanceof LabelDeleted,
        ));
        self::assertCount(1, $events);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($label->id()->toString(), $events[0]->labelId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testThrowsWhenActorIsNotAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $label = Label::create(Uuid::uuid4(), $board->id(), 'Old', '#000000');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->labelRepository->expects($this->never())->method('remove');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($label, $board, $member)
            ->__invoke(new DeleteLabelCommand((string) $label->id(), (string) $actorId));
    }

    public function testThrowsWhenLabelMissing(): void
    {
        $this->labelRepository->expects($this->never())->method('remove');
        $this->expectException(LabelNotFoundException::class);

        $this->handler(null, null, null)
            ->__invoke(new DeleteLabelCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }
}
