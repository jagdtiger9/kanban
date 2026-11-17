<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\Mcp\Application\Message\Command\IssueMcpTokenCommand;
use App\Mcp\Application\Service\IssuedMcpToken;
use App\Shared\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class McpTokenController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function issue(IssueMcpTokenCommand $command, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }
        $command->actorId = (string)$user->getId();

        /** @var IssuedMcpToken $issued */
        $issued = $this->commandBus->dispatch($command);

        return $this->json([
            'token' => $issued->token,
            'expiresAt' => $issued->expiresAt->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
