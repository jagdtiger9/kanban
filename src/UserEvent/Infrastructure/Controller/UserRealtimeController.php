<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\UserEvent\Application\Message\Query\GetUserRealtimeTokenQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

final class UserRealtimeController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function token(GetUserRealtimeTokenQuery $query, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        $query->actorId = (string) $user->getId();

        $token = $this->queryBus->query($query);

        return $this->json([
            'url' => $token->url,
            'channel' => $token->channel,
            'token' => $token->token,
            'subscriptionToken' => $token->subscriptionToken,
            'expiresAt' => $token->expiresAt,
        ]);
    }
}
