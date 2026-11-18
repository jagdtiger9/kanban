<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\RoomBroadcaster;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Clock\MockClock;

/**
 * A project with two members (Alice, Bob) and an outsider (Eve), plus
 * in-memory rooms and a recording publisher.
 */
abstract class CallFixture extends TestCase
{
    protected Project $project;
    protected User $alice;
    protected User $bob;
    protected User $eve;
    protected InMemoryRoomRepository $rooms;
    protected RecordingCallPublisher $publisher;
    protected MockClock $clock;
    protected CallSettings $settings;
    protected CallAccess $access;
    protected RoomBroadcaster $broadcaster;

    protected function setUp(): void
    {
        $this->project = Project::create(Uuid::uuid4(), 'Kanban');
        $this->alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');
        $this->eve = User::create(Uuid::uuid4(), 'eve@example.com', 'hash', 'Eve');
        $users = [$this->alice, $this->bob, $this->eve];
        $members = [
            ProjectMember::create(Uuid::uuid4(), $this->project->id(), $this->alice->id(), MemberRole::Admin),
            ProjectMember::create(Uuid::uuid4(), $this->project->id(), $this->bob->id(), MemberRole::Member),
        ];

        $projectRepository = $this->createStub(ProjectRepository::class);
        $projectRepository->method('findById')->willReturnCallback(
            fn(UuidInterface $id): ?Project => $id->equals($this->project->id()) ? $this->project : null,
        );
        $projectRepository->method('findByMember')->willReturnCallback(
            fn(UuidInterface $id): array => $id->equals($this->eve->id()) ? [] : [$this->project],
        );

        $memberRepository = $this->createStub(ProjectMemberRepository::class);
        $memberRepository->method('findByProject')->willReturn($members);
        $memberRepository->method('findByProjectAndUser')->willReturnCallback(
            static function (UuidInterface $projectId, UuidInterface $userId) use ($members): ?ProjectMember {
                foreach ($members as $member) {
                    if ($member->projectId()->equals($projectId) && $member->userId()->equals($userId)) {
                        return $member;
                    }
                }

                return null;
            },
        );

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findById')->willReturnCallback(
            static function (UuidInterface $id) use ($users): ?User {
                foreach ($users as $user) {
                    if ($user->id()->equals($id)) {
                        return $user;
                    }
                }

                return null;
            },
        );

        $this->rooms = new InMemoryRoomRepository();
        $this->publisher = new RecordingCallPublisher();
        $this->clock = new MockClock('2026-11-18 12:00:00+00:00');
        $this->settings = new CallSettings(4, 60);
        $this->access = new CallAccess($projectRepository, new MembershipGuard($memberRepository, $userRepository));
        $this->broadcaster = new RoomBroadcaster($memberRepository, $userRepository, $this->publisher);
    }

    protected function projectId(): string
    {
        return (string) $this->project->id();
    }

    protected function id(User $user): string
    {
        return (string) $user->id();
    }
}
