<?php

declare(strict_types=1);

namespace App\User\Application\MessageHandler;

use App\Shared\Application\Storage\FileStorage;
use App\User\Application\Message\Query\GetAvatarQuery;
use App\User\Domain\Exception\AvatarNotFoundException;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetAvatarQueryHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private FileStorage $storage,
    ) {
    }

    /**
     * @return array{stream: resource}
     */
    public function __invoke(GetAvatarQuery $query): array
    {
        $user = $this->userRepository->findById(Uuid::fromString($query->userId));
        if ($user === null) {
            throw new UserNotFoundException('User not found');
        }

        $storageKey = $user->avatarStorageKey();
        if ($storageKey === null) {
            throw new AvatarNotFoundException('Avatar not found');
        }

        return ['stream' => $this->storage->open($storageKey)];
    }
}
