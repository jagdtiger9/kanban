<?php

declare(strict_types=1);

namespace App\News\Application\MessageHandler;

use App\News\Application\Message\Command\DeleteNewsPostCommand;
use App\News\Application\Service\NewsGuard;
use App\News\Domain\Exception\NewsPostNotFoundException;
use App\News\Domain\Repository\NewsPostRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteNewsPostCommandHandler
{
    public function __construct(
        private NewsPostRepository $newsPostRepository,
        private NewsGuard $newsGuard,
    ) {
    }

    public function __invoke(DeleteNewsPostCommand $command): void
    {
        $post = $this->newsPostRepository->findById(Uuid::fromString($command->newsPostId));
        if ($post === null) {
            throw new NewsPostNotFoundException('News post not found');
        }

        $actorId = Uuid::fromString($command->actorId);
        $this->newsGuard->requireAuthorOrGlobalAdmin($post, $actorId);

        $post->delete($actorId);
        $this->newsPostRepository->remove($post);
    }
}
