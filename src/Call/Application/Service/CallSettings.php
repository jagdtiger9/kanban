<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CallSettings
{
    public function __construct(
        #[Autowire('%call.max_participants%')]
        public int $maxParticipants,
        #[Autowire('%call.participant_ttl%')]
        public int $participantTtl,
    ) {
    }
}
