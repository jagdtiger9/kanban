<?php

declare(strict_types=1);

namespace App\News\Domain\Repository;

use App\News\Domain\Entity\NewsPost;
use Ramsey\Uuid\UuidInterface;

interface NewsPostRepository
{
    public function save(NewsPost $newsPost): void;

    public function findById(UuidInterface $id): ?NewsPost;

    /**
     * Newest first (createdAt DESC, id DESC).
     *
     * @return list<NewsPost>
     */
    public function findPage(int $page, int $perPage): array;

    public function remove(NewsPost $newsPost): void;
}
