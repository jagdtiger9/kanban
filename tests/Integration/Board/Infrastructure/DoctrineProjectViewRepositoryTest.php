<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\ProjectView;
use App\Board\Domain\Repository\ProjectViewRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineProjectViewRepositoryTest extends DatabaseTestCase
{
    private ProjectViewRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(ProjectViewRepository::class);
    }

    public function testSaveAndFindByUserAndProjectRoundtrip(): void
    {
        $userId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $view = ProjectView::create(Uuid::uuid4(), $userId, $projectId);
        $this->repo->save($view);
        $this->em()->clear();

        $found = $this->repo->findByUserAndProject($userId, $projectId);

        self::assertInstanceOf(ProjectView::class, $found);
        self::assertTrue($found->id()->equals($view->id()));
        self::assertSame(
            $view->firstSeenAt()->format('U.u'),
            $found->firstSeenAt()->format('U.u'),
        );
    }

    public function testFindByUserAndProjectReturnsNullForUnknownPair(): void
    {
        self::assertNull($this->repo->findByUserAndProject(Uuid::uuid4(), Uuid::uuid4()));
    }
}
