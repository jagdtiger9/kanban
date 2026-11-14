<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Query\GetRealtimeTokenQuery;
use App\Board\Application\MessageHandler\GetRealtimeTokenQueryHandler;
use App\Board\Application\Service\RealtimeToken;
use App\Board\Application\Service\RealtimeTokenIssuerInterface;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GetRealtimeTokenQueryHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(
        ?Project $project,
        ?ProjectMember $actorMembership,
        ?User $actorUser,
        RealtimeTokenIssuerInterface $issuer,
    ): GetRealtimeTokenQueryHandler {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->userRepository->method('findById')->willReturn($actorUser);

        return new GetRealtimeTokenQueryHandler(
            $this->projectRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
            $issuer,
        );
    }

    private function token(): RealtimeToken
    {
        return new RealtimeToken('wss://example.test/connection/websocket', 'project:x', 't', 'st', 123);
    }

    public function testMemberReceivesTokenForProjectChannel(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $expected = $this->token();
        $issuer = $this->createMock(RealtimeTokenIssuerInterface::class);
        $issuer->expects($this->once())
            ->method('issue')
            ->with(
                $this->callback(static fn (UuidInterface $id): bool => $id->equals($actorId)),
                'project:' . $project->id(),
            )
            ->willReturn($expected);

        $result = $this->handler($project, $member, null, $issuer)
            ->__invoke(new GetRealtimeTokenQuery((string) $project->id(), (string) $actorId));

        self::assertSame($expected, $result);
    }

    public function testGlobalAdminReceivesTokenWithoutMembership(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $globalAdmin = User::create($actorId, 'admin@example.com', 'hash', 'Admin');
        $globalAdmin->promoteToGlobalAdmin();

        $issuer = $this->createMock(RealtimeTokenIssuerInterface::class);
        $issuer->expects($this->once())->method('issue')->willReturn($this->token());

        $this->handler($project, null, $globalAdmin, $issuer)
            ->__invoke(new GetRealtimeTokenQuery((string) $project->id(), (string) $actorId));
    }

    public function testStrangerIsForbidden(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');

        $issuer = $this->createMock(RealtimeTokenIssuerInterface::class);
        $issuer->expects($this->never())->method('issue');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null, null, $issuer)
            ->__invoke(new GetRealtimeTokenQuery((string) $project->id(), (string) Uuid::uuid4()));
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $issuer = $this->createMock(RealtimeTokenIssuerInterface::class);
        $issuer->expects($this->never())->method('issue');

        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null, null, $issuer)
            ->__invoke(new GetRealtimeTokenQuery((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }
}
