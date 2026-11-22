<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Application\Message\Command\UpdateAvatarColorCommand;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

final class UserColorController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function update(UpdateAvatarColorCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->colorPayload($this->commandBus->dispatch($command)));
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
     * @return array{avatarColor: ?string}
     */
    private function colorPayload(User $user): array
    {
        return ['avatarColor' => $user->avatarColor()];
    }
}
