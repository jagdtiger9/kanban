<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Application\Message\Command\RemoveAvatarCommand;
use App\User\Application\Message\Command\UpdateAvatarCommand;
use App\User\Application\Message\Query\GetAvatarQuery;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserAvatarController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function update(UpdateAvatarCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->avatarPayload($this->commandBus->dispatch($command)));
    }

    public function delete(RemoveAvatarCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function get(GetAvatarQuery $query): StreamedResponse
    {
        $result = $this->queryBus->query($query);

        return new StreamedResponse(
            static function () use ($result): void {
                fpassthru($result['stream']);
                fclose($result['stream']);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/webp',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    'avatar.webp',
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; sandbox",
            ],
        );
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
     * @return array{avatarVersion: ?string}
     */
    private function avatarPayload(User $user): array
    {
        return ['avatarVersion' => $user->avatarVersion()];
    }
}
