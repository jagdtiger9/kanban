<?php

declare(strict_types=1);

namespace App\News\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class NewsPostDeleted implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $newsPostId,
        ?string $actorId,
    ) {
        $this->initEvent($newsPostId, $actorId);
    }
}
