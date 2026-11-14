<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Domain\Entity;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectRestored;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProjectTest extends TestCase
{
    public function testCreateTrimsName(): void
    {
        $project = Project::create(Uuid::uuid4(), '  My Project  ');

        self::assertSame('My Project', $project->name());
        self::assertFalse($project->isArchived());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Project::create(Uuid::uuid4(), '   ');
    }

    public function testArchiveIsIdempotent(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');
        $actorId = Uuid::uuid4();

        $project->archive($actorId);

        $events = $project->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProjectArchived::class, $events[0]);
        self::assertTrue($project->isArchived());
        self::assertSame($project->id()->toString(), $events[0]->projectId);
        self::assertSame($actorId->toString(), $events[0]->actorId);

        $project->archive($actorId);
        self::assertSame([], $project->releaseEvents());
    }

    public function testRestoreRecordsNoEventWhenNotArchived(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');

        $project->restore(Uuid::uuid4());

        self::assertSame([], $project->releaseEvents());
    }

    public function testRestoreAfterArchive(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');
        $actorId = Uuid::uuid4();
        $project->archive($actorId);
        $project->releaseEvents();

        $project->restore($actorId);

        $events = $project->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProjectRestored::class, $events[0]);
        self::assertSame($project->id()->toString(), $events[0]->projectId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertFalse($project->isArchived());
    }
}
