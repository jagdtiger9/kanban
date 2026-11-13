<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Board;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class BoardTest extends TestCase
{
    public function testCreateTrimsName(): void
    {
        $projectId = Uuid::uuid4();
        $board = Board::create(Uuid::uuid4(), $projectId, '  Default Board  ');

        self::assertSame('Default Board', $board->name());
        self::assertTrue($board->projectId()->equals($projectId));
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Board::create(Uuid::uuid4(), Uuid::uuid4(), '  ');
    }
}
