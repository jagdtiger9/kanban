<?php

declare(strict_types=1);

namespace App\News\Application\MessageHandler;

use App\News\Application\Message\Command\CreateNewsPostCommand;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateNewsPostCommandHandler
{
    public function __construct(
        private NewsPostRepository $newsPostRepository,
        private NewsGuard $newsGuard,
    ) {
    }

    /**
     * @return array{post: NewsPost, author: User}
     */
    public function __invoke(CreateNewsPostCommand $command): array
    {
        $author = $this->newsGuard->requirePostAccess(Uuid::fromString($command->actorId));

        $post = NewsPost::create(Uuid::uuid4(), $author->id(), $command->body);
        $this->newsPostRepository->save($post);

        return ['post' => $post, 'author' => $author];
    }
}
