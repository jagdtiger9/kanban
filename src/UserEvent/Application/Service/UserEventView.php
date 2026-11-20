<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Service;

use App\UserEvent\Domain\Entity\UserEvent;

/**
 * API and realtime representation of a feed item.
 */
final class UserEventView
{
    /** @return array<string, mixed> */
    public static function item(UserEvent $event): array
    {
        $payload = $event->payload();

        return [
            'id' => (string) $event->id(),
            'type' => $event->type()->value,
            'actorId' => $event->actorId() === null ? null : (string) $event->actorId(),
            'actorName' => $payload['actorName'] ?? null,
            'projectId' => $event->projectId() === null ? null : (string) $event->projectId(),
            'projectName' => $payload['projectName'] ?? null,
            'cardId' => $event->cardId() === null ? null : (string) $event->cardId(),
            'cardTitle' => $payload['cardTitle'] ?? null,
            'role' => $payload['role'] ?? null,
            'occurredOn' => $event->occurredOn()->format(\DateTimeInterface::ATOM),
            'seenAt' => $event->seenAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
