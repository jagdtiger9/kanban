<?php

declare(strict_types=1);

namespace App\News\Application\MessageHandler;

use App\News\Application\Message\Command\UpdateNewsPostCommand;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Exception\NewsPostNotFoundException;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateNewsPostCommandHandler
{
    public function __construct(
        private NewsPostRepository $newsPostRepository,
        private UserRepository $userRepository,
        private NewsGuard $newsGuard,
    ) {
    }

    /**
     * @return array{post: NewsPost, author: User}
     */
    public function __invoke(UpdateNewsPostCommand $command): array
    {
        $post = $this->newsPostRepository->findById(Uuid::fromString($command->newsPostId));
        if ($post === null) {
            throw new NewsPostNotFoundException('News post not found');
        }

        $this->newsGuard->requireAuthorOrGlobalAdmin($post, Uuid::fromString($command->actorId));

        $post->edit($command->body);
        $this->newsPostRepository->save($post);

        $author = $this->userRepository->findById($post->authorId());
        if ($author === null) {
            throw new \LogicException('Author must exist for every news post');
        }

        return ['post' => $post, 'author' => $author];
    }
}
