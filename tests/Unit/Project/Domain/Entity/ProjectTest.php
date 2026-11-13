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

        $event = $project->archive($actorId);

        self::assertInstanceOf(ProjectArchived::class, $event);
        self::assertTrue($project->isArchived());
        self::assertTrue($event->projectId->equals($project->id()));
        self::assertTrue($event->actorId->equals($actorId));
        self::assertNull($project->archive($actorId));
    }

    public function testRestoreReturnsNullWhenNotArchived(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');

        self::assertNull($project->restore(Uuid::uuid4()));
    }

    public function testRestoreAfterArchive(): void
    {
        $project = Project::create(Uuid::uuid4(), 'My Project');
        $project->archive(Uuid::uuid4());

        $event = $project->restore(Uuid::uuid4());

        self::assertInstanceOf(ProjectRestored::class, $event);
        self::assertFalse($project->isArchived());
    }
}
