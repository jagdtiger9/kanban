<?php

declare(strict_types=1);

namespace App\News\Application\MessageHandler;

use App\News\Application\Message\Query\NewsListQuery;
use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NewsListQueryHandler
{
    public function __construct(
        private NewsPostRepository $newsPostRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{posts: list<NewsPost>, authors: array<string, User>}
     */
    public function __invoke(NewsListQuery $query): array
    {
        $posts = $this->newsPostRepository->findPage($query->p, $query->perPage);

        $authors = [];
        foreach ($posts as $post) {
            $authorId = (string) $post->authorId();
            if (isset($authors[$authorId])) {
                continue;
            }

            $author = $this->userRepository->findById($post->authorId());
            if ($author === null) {
                throw new \LogicException('Author must exist for every news post');
            }
            $authors[$authorId] = $author;
        }

        return ['posts' => $posts, 'authors' => $authors];
    }
}
