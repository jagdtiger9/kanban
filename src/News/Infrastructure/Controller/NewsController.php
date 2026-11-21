<?php

declare(strict_types=1);

namespace App\News\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\News\Application\Message\Command\CreateNewsPostCommand;
use App\News\Application\Message\Command\DeleteNewsPostCommand;
use App\News\Application\Message\Command\UpdateNewsPostCommand;
use App\News\Application\Message\Query\NewsListQuery;
use App\News\Domain\Entity\NewsPost;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class NewsController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function list(NewsListQuery $query): JsonResponse
    {
        $result = $this->queryBus->query($query);

        return $this->json(array_map(
            fn(NewsPost $post): array => $this->newsPostPayload(
                $post,
                $result['authors'][(string)$post->authorId()],
            ),
            $result['posts'],
        ));
    }

    public function create(CreateNewsPostCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        $result = $this->commandBus->dispatch($command);

        return $this->json(
            $this->newsPostPayload($result['post'], $result['author']),
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateNewsPostCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        $result = $this->commandBus->dispatch($command);

        return $this->json($this->newsPostPayload($result['post'], $result['author']));
    }

    public function delete(DeleteNewsPostCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string)$user->getId();
    }

    /**
     * @return array{
     *     id: string,
     *     authorId: string,
     *     authorName: string,
     *     authorAvatarVersion: ?string,
     *     authorAvatarColor: ?string,
     *     body: string,
     *     createdAt: string,
     *     updatedAt: string,
     * }
     */
    private function newsPostPayload(NewsPost $post, User $author): array
    {
        return [
            'id' => (string)$post->id(),
            'authorId' => (string)$post->authorId(),
            'authorName' => $author->displayName(),
            'authorAvatarVersion' => $author->avatarVersion(),
            'authorAvatarColor' => $author->avatarColor(),
            'body' => $post->body(),
            'createdAt' => $post->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $post->updatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
