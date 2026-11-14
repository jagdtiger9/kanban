<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Service;

use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\ColumnReordered;
use App\Board\Domain\Service\ColumnReorderService;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ColumnReorderServiceTest extends TestCase
{
    private function column(string $name, int $position): Column
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), $name, $position);
        $column->releaseEvents();

        return $column;
    }

    public function testReorderMovesColumnAndRenumbers(): void
    {
        $a = $this->column('A', 0);
        $b = $this->column('B', 1);
        $c = $this->column('C', 2);
        $actorId = Uuid::uuid4();
        $service = new ColumnReorderService();

        $service->reorder($a, 2, [$a, $b, $c], $actorId);

        self::assertSame(0, $b->position());
        self::assertSame(1, $c->position());
        self::assertSame(2, $a->position());

        $events = $a->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ColumnReordered::class, $events[0]);
        self::assertSame($a->boardId()->toString(), $events[0]->boardId);
        self::assertSame($a->id()->toString(), $events[0]->columnId);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testTargetIndexClampedToEnd(): void
    {
        $a = $this->column('A', 0);
        $b = $this->column('B', 1);
        $service = new ColumnReorderService();

        $service->reorder($a, 99, [$a, $b]);

        self::assertSame(1, $a->position());
        self::assertSame(0, $b->position());
    }

    public function testRejectsNegativeTargetIndex(): void
    {
        $a = $this->column('A', 0);
        $service = new ColumnReorderService();

        $this->expectException(\InvalidArgumentException::class);
        $service->reorder($a, -1, [$a]);
    }

    public function testRenumberMakesPositionsDense(): void
    {
        $a = $this->column('A', 0);
        $b = $this->column('B', 2);
        $c = $this->column('C', 5);
        $service = new ColumnReorderService();

        $service->renumber([$a, $b, $c]);

        self::assertSame(0, $a->position());
        self::assertSame(1, $b->position());
        self::assertSame(2, $c->position());
    }
}
