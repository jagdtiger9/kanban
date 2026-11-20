<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\UserEvent\Domain\Enum\UserEventType;
use Ramsey\Uuid\UuidInterface;

final class RecipientResolver
{
    /** @param iterable<RecipientRule> $rules */
    public function __construct(private iterable $rules)
    {
    }

    public function resolve(DomainEventInterface $event): ?ResolvedRecipients
    {
        $type = null;
        $userIds = [];

        foreach ($this->rules as $rule) {
            if (!$rule->supports($event)) {
                continue;
            }

            $type ??= $rule->type();
            foreach ($rule->recipients($event) as $userId) {
                $userIds[$userId->toString()] = $userId;
            }
        }

        if ($type === null) {
            return null;
        }

        $actorId = $event->getActorId();
        if ($actorId !== null) {
            unset($userIds[$actorId]);
        }

        return new ResolvedRecipients($type, array_values($userIds));
    }
}
