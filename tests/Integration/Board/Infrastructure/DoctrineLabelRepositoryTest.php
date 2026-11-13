<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\LabelRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineLabelRepositoryTest extends DatabaseTestCase
{
    private LabelRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(LabelRepository::class);
    }

    public function testSaveFindByIdAndFindByBoard(): void
    {
        $boardId = Uuid::uuid4();
        $label = Label::create(Uuid::uuid4(), $boardId, 'Bug', '#ff0000');
        $this->repo->save($label);
        $this->em()->clear();

        $found = $this->repo->findById($label->id());
        self::assertNotNull($found);
        self::assertSame('#ff0000', $found->color());

        $byBoard = $this->repo->findByBoard($boardId);
        self::assertCount(1, $byBoard);
        self::assertTrue($byBoard[0]->id()->equals($label->id()));

        self::assertSame([], $this->repo->findByBoard(Uuid::uuid4()));
    }

    public function testRemove(): void
    {
        $label = Label::create(Uuid::uuid4(), Uuid::uuid4(), 'Bug', '#ff0000');
        $this->repo->save($label);
        $this->em()->clear();

        $managed = $this->repo->findById($label->id());
        self::assertNotNull($managed);
        $this->repo->remove($managed);
        $this->em()->clear();

        self::assertNull($this->repo->findById($label->id()));
    }
}
