<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\UpdateLabelCommand;
use App\Board\Application\MessageHandler\UpdateLabelCommandHandler;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Exception\LabelNotFoundException;
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

final class UpdateLabelCommandHandlerTest extends TestCase
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

    private function handler(?Label $label, ?Board $board, ?ProjectMember $actorMembership): UpdateLabelCommandHandler
    {
        $this->labelRepository->method('findById')->willReturn($label);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new UpdateLabelCommandHandler(
            $this->labelRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testRenamesAndRecolors(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $label = Label::create(Uuid::uuid4(), $board->id(), 'Old', '#000000');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->labelRepository->expects($this->once())->method('save');

        $result = $this->handler($label, $board, $member)
            ->__invoke(new UpdateLabelCommand((string) $label->id(), 'New', '#ffffff', (string) $actorId));

        self::assertSame($label, $result);
        self::assertSame('New', $label->name());
        self::assertSame('#ffffff', $label->color());
    }

    public function testThrowsWhenLabelMissing(): void
    {
        $this->labelRepository->expects($this->never())->method('save');
        $this->expectException(LabelNotFoundException::class);

        $this->handler(null, null, null)
            ->__invoke(new UpdateLabelCommand((string) Uuid::uuid4(), 'New', '#ffffff', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $label = Label::create(Uuid::uuid4(), $board->id(), 'Old', '#000000');

        $this->labelRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($label, $board, null)
            ->__invoke(new UpdateLabelCommand((string) $label->id(), 'New', '#ffffff', (string) Uuid::uuid4()));
    }
}
