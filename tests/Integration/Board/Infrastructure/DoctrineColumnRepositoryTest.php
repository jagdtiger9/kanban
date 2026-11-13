<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\ColumnRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineColumnRepositoryTest extends DatabaseTestCase
{
    private ColumnRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(ColumnRepository::class);
    }

    public function testFindByBoardOrdersByPosition(): void
    {
        $boardId = Uuid::uuid4();
        $c2 = Column::create(Uuid::uuid4(), $boardId, 'Third', 2);
        $c0 = Column::create(Uuid::uuid4(), $boardId, 'First', 0);
        $c1 = Column::create(Uuid::uuid4(), $boardId, 'Second', 1);
        foreach ([$c2, $c0, $c1] as $column) {
            $this->repo->save($column);
        }
        $this->em()->clear();

        $columns = $this->repo->findByBoard($boardId);

        self::assertSame(['First', 'Second', 'Third'], array_map(
            static fn (Column $c): string => $c->name(),
            $columns,
        ));
    }

    public function testRemove(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'Doomed', 0);
        $this->repo->save($column);
        $this->em()->clear();

        $loaded = $this->repo->findById($column->id());
        $this->repo->remove($loaded);
        $this->em()->clear();

        self::assertNull($this->repo->findById($column->id()));
    }
}
