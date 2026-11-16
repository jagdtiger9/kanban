<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Board\Application\Message\Query\GetRealtimeTokenQuery;
use App\Shared\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

final class RealtimeController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function token(GetRealtimeTokenQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        $token = $this->queryBus->query($query);

        return $this->json([
            'url' => $token->url,
            'channel' => $token->channel,
            'token' => $token->token,
            'subscriptionToken' => $token->subscriptionToken,
            'expiresAt' => $token->expiresAt,
        ]);
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
