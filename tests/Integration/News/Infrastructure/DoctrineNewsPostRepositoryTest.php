<?php

declare(strict_types=1);

namespace App\Tests\Integration\News\Infrastructure;

use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineNewsPostRepositoryTest extends DatabaseTestCase
{
    private NewsPostRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(NewsPostRepository::class);
    }

    public function testSaveFindEditAndRemove(): void
    {
        $authorId = Uuid::uuid4();
        $post = NewsPost::create(Uuid::uuid4(), $authorId, 'Hello');
        $this->repo->save($post);
        $this->em()->clear();

        $loaded = $this->repo->findById($post->id());
        self::assertNotNull($loaded);
        self::assertSame('Hello', $loaded->body());
        self::assertTrue($loaded->authorId()->equals($authorId));

        $loaded->edit('Edited');
        $this->repo->save($loaded);
        $this->em()->clear();

        self::assertSame('Edited', $this->repo->findById($post->id())?->body());

        $reloaded = $this->repo->findById($post->id());
        self::assertNotNull($reloaded);
        $this->repo->remove($reloaded);
        $this->em()->clear();

        self::assertNull($this->repo->findById($post->id()));
    }

    public function testFindPageOrdersNewestFirstAndPaginates(): void
    {
        foreach (['First', 'Second', 'Third', 'Fourth', 'Fifth'] as $body) {
            $this->repo->save(NewsPost::create(Uuid::uuid4(), Uuid::uuid4(), $body));
            usleep(2000);
        }
        $this->em()->clear();

        $page = fn(int $p): array => array_map(
            static fn(NewsPost $post): string => $post->body(),
            $this->repo->findPage($p, 2),
        );

        self::assertSame(['Fifth', 'Fourth'], $page(1));
        self::assertSame(['Third', 'Second'], $page(2));
        self::assertSame(['First'], $page(3));
        self::assertSame([], $page(4));
    }
}
