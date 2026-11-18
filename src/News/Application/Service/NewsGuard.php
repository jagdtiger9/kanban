<?php

declare(strict_types=1);

namespace App\News\Application\Service;

use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Exception\NewsPostForbiddenException;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\UuidInterface;

final class NewsGuard
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function requirePostAccess(UuidInterface $actorId): User
    {
        $user = $this->userRepository->findById($actorId);
        if ($user === null || (!$user->isGlobalAdmin() && !$user->isNewsMaker())) {
            throw new AccessForbiddenException('Only global admins and news makers can post news');
        }

        return $user;
    }

    public function requireAuthorOrGlobalAdmin(NewsPost $newsPost, UuidInterface $actorId): void
    {
        if ($newsPost->authorId()->equals($actorId)) {
            return;
        }

        $user = $this->userRepository->findById($actorId);
        if ($user === null || !$user->isGlobalAdmin()) {
            throw new NewsPostForbiddenException('Only the author or a global admin can modify this post');
        }
    }
}
