<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\UserEvent\Application\Message\Command\MarkUserEventsSeenCommand;
use App\UserEvent\Application\Message\Query\CountUnreadUserEventsQuery;
use App\UserEvent\Application\Message\Query\ListUserEventsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class UserEventController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    public function index(ListUserEventsQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        return $this->json($this->queryBus->query($query));
    }

    public function unreadCount(CountUnreadUserEventsQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        return $this->json(['count' => $this->queryBus->query($query)]);
    }

    public function seen(MarkUserEventsSeenCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string) $user->getId();
    }
}
