<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Column;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ColumnTest extends TestCase
{
    public function testCreateTrimsName(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), '  To Do  ', 0);

        self::assertSame('To Do', $column->name());
        self::assertSame(0, $column->position());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Column::create(Uuid::uuid4(), Uuid::uuid4(), ' ', 0);
    }

    public function testCreateRejectsNegativePosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', -1);
    }

    public function testRenameRejectsEmpty(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', 0);

        $this->expectException(\InvalidArgumentException::class);
        $column->rename('  ');
    }

    public function testChangePositionRejectsNegative(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', 0);

        $this->expectException(\InvalidArgumentException::class);
        $column->changePosition(-1);
    }
}
