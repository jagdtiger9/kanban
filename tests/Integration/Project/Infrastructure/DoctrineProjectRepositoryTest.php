<?php

declare(strict_types=1);

namespace App\Tests\Integration\Project\Infrastructure;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineProjectRepositoryTest extends DatabaseTestCase
{
    private ProjectRepository $repo;
    private ProjectMemberRepository $members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(ProjectRepository::class);
        $this->members = $this->repo(ProjectMemberRepository::class);
    }

    public function testSaveAndFindById(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');
        $this->repo->save($project);
        $this->em()->clear();

        $found = $this->repo->findById($project->id());

        self::assertNotNull($found);
        self::assertSame('My Project', $found->name());
    }

    public function testMemberLifecycle(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $userId = Uuid::uuid4();
        $this->repo->save($project);

        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $userId, MemberRole::Member);
        $this->members->save($member);
        $this->em()->clear();

        $found = $this->members->findByProjectAndUser($project->id(), $userId);
        self::assertNotNull($found);
        self::assertSame(MemberRole::Member, $found->role());

        $found->changeRole(MemberRole::Admin, Uuid::uuid4());
        $this->members->save($found);
        $this->em()->clear();

        $reloaded = $this->members->findByProjectAndUser($project->id(), $userId);
        self::assertSame(MemberRole::Admin, $reloaded->role());

        $this->members->remove($reloaded);
        $this->em()->clear();

        self::assertNull($this->members->findByProjectAndUser($project->id(), $userId));
    }

    public function testFindByMemberFiltersAndOrdersNewestFirst(): void
    {
        $userId = Uuid::uuid4();
        $otherUserId = Uuid::uuid4();

        $first = Project::create(Uuid::uuid4(), 'First');
        $second = Project::create(Uuid::uuid4(), 'Second');
        $theirs = Project::create(Uuid::uuid4(), 'Theirs');
        $this->repo->save($first);
        $this->repo->save($second);
        $this->repo->save($theirs);
        $this->members->save(ProjectMember::create(Uuid::uuid4(), $first->id(), $userId, MemberRole::Member));
        $this->members->save(ProjectMember::create(Uuid::uuid4(), $second->id(), $userId, MemberRole::Admin));
        $this->members->save(ProjectMember::create(Uuid::uuid4(), $theirs->id(), $otherUserId, MemberRole::Admin));
        $this->em()->clear();

        $projects = $this->repo->findByMember($userId);

        self::assertCount(2, $projects);
        self::assertTrue($projects[0]->id()->equals($second->id()));
        self::assertTrue($projects[1]->id()->equals($first->id()));
    }

    public function testFindByProjectReturnsMembersOrderedByJoin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $this->repo->save($project);
        $first = ProjectMember::create(Uuid::uuid4(), $project->id(), Uuid::uuid4(), MemberRole::Admin);
        $this->members->save($first);
        $second = ProjectMember::create(Uuid::uuid4(), $project->id(), Uuid::uuid4(), MemberRole::Member);
        $this->members->save($second);
        $this->em()->clear();

        $members = $this->members->findByProject($project->id());

        self::assertCount(2, $members);
        self::assertTrue($members[0]->id()->equals($first->id()));
        self::assertTrue($members[1]->id()->equals($second->id()));
    }

    public function testRemoveDeletesProject(): void
    {
        // the member rows are removed by the DB-level FK cascade from the
        // migration; the SchemaTool-built test schema has no FKs, so only the
        // project row itself is asserted here
        $project = Project::create(Uuid::uuid4(), 'Doomed');
        $this->repo->save($project);

        $this->repo->remove($project);
        $this->em()->clear();

        self::assertNull($this->repo->findById($project->id()));
    }
}
