<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Repository\BoardRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineBoardRepositoryTest extends DatabaseTestCase
{
    private BoardRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(BoardRepository::class);
    }

    public function testSaveFindByIdAndFindByProject(): void
    {
        $projectId = Uuid::uuid4();
        $board = Board::create(Uuid::uuid4(), $projectId, 'Board');
        $this->repo->save($board);
        $this->em()->clear();

        self::assertNotNull($this->repo->findById($board->id()));

        $byProject = $this->repo->findByProject($projectId);
        self::assertNotNull($byProject);
        self::assertTrue($byProject->id()->equals($board->id()));

        self::assertNull($this->repo->findByProject(Uuid::uuid4()));
    }
}
