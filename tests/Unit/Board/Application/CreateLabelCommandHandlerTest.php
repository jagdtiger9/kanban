<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\CreateLabelCommand;
use App\Board\Application\MessageHandler\CreateLabelCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Event\LabelCreated;
use App\Board\Domain\Repository\BoardRepository;
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

final class CreateLabelCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private BoardRepository $boardRepository;
    private LabelRepository $labelRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->labelRepository = $this->createMock(LabelRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Project $project, ?Board $board, ?ProjectMember $actorMembership): CreateLabelCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->boardRepository->method('findByProject')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new CreateLabelCommandHandler(
            $this->projectRepository,
            $this->boardRepository,
            $this->labelRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testCreatesLabelOnProjectBoard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->labelRepository->expects($this->once())->method('save');

        $label = $this->handler($project, $board, $member)
            ->__invoke(new CreateLabelCommand((string) $project->id(), 'Bug', '#ff0000', (string) $actorId));

        $events = $label->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LabelCreated::class, $events[0]);
        self::assertSame($board->id()->toString(), $events[0]->boardId);
        self::assertSame($label->id()->toString(), $events[0]->labelId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertTrue($label->boardId()->equals($board->id()));
        self::assertSame('Bug', $label->name());
        self::assertSame('#ff0000', $label->color());
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $this->labelRepository->expects($this->never())->method('save');
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null, null)
            ->__invoke(new CreateLabelCommand((string) Uuid::uuid4(), 'Bug', '#ff0000', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');

        $this->labelRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, $board, null)
            ->__invoke(new CreateLabelCommand((string) $project->id(), 'Bug', '#ff0000', (string) Uuid::uuid4()));
    }
}
