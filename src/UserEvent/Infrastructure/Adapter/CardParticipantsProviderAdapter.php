<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Adapter;

use App\Board\Domain\Repository\CardRepository;
use App\User\Domain\Entity\User;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardParticipantsProvider::class)]
final class CardParticipantsProviderAdapter implements CardParticipantsProvider
{
    public function __construct(private CardRepository $cards)
    {
    }

    public function assigneeIds(UuidInterface $cardId): array
    {
        $card = $this->cards->findById($cardId);
        if ($card === null) {
            return [];
        }

        return array_values(
            array_map(
                static fn(User $user): UuidInterface => $user->id(),
                $card->assignees()->toArray(),
            )
        );
    }

    public function creatorId(UuidInterface $cardId): ?UuidInterface
    {
        return $this->cards->findById($cardId)?->creatorId();
    }
}
