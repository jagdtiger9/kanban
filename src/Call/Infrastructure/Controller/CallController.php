<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Call\Application\Message\Command\CallHeartbeatCommand;
use App\Call\Application\Message\Command\DeclineCallCommand;
use App\Call\Application\Message\Command\InviteToCallCommand;
use App\Call\Application\Message\Command\JoinCallCommand;
use App\Call\Application\Message\Command\LeaveCallCommand;
use App\Call\Application\Message\Command\SendCallSignalCommand;
use App\Call\Application\Message\Query\CheckCallsQuery;
use App\Call\Application\Message\Query\GetCallTokenQuery;
use App\Call\Application\Message\Query\ListActiveCallsQuery;
use App\Call\Application\Service\CallCheck;
use App\Call\Application\Service\CallsCheckReport;
use App\Call\Domain\Exception\NotInRoomException;
use App\Call\Domain\Exception\RoomFullException;
use App\Shared\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Calls between project members (src/Call). Call-specific exceptions are
 * mapped here so the shared exception subscriber stays untouched.
 */
final class CallController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function token(GetCallTokenQuery $query, Security $security): JsonResponse
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

    /**
     * Calls diagnostics for the check page: server-side checks plus the ICE servers
     * the browser uses for its own STUN/TURN test.
     */
    public function check(CheckCallsQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);
        /** @var CallsCheckReport $report */
        $report = $this->queryBus->query($query);

        return $this->json([
            'checks' => array_map(static fn(CallCheck $check): array => $check->toArray(), $report->checks),
            'iceServers' => $report->iceServers,
        ]);
    }

    public function rooms(ListActiveCallsQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        return $this->json(['calls' => $this->queryBus->query($query)]);
    }

    public function join(JoinCallCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);
        $joined = $this->run(fn() => $this->commandBus->dispatch($command));

        return $this->json([
            'participants' => $joined->participants,
            'iceServers' => $joined->iceServers,
        ]);
    }

    public function leave(LeaveCallCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function heartbeat(CallHeartbeatCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json(['inRoom' => $this->commandBus->dispatch($command)]);
    }

    public function signal(SendCallSignalCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->run(fn() => $this->commandBus->dispatch($command));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function invite(InviteToCallCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->run(fn() => $this->commandBus->dispatch($command));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function decline(DeclineCallCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    private function run(callable $action): mixed
    {
        try {
            return $action();
        } catch (RoomFullException|NotInRoomException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
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
