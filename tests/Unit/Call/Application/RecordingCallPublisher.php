<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Service\CallPublisher;

final class RecordingCallPublisher implements CallPublisher
{
    /** @var list<array{userIds: list<string>, data: array<string, mixed>}> */
    public array $published = [];

    public function publish(array $userIds, array $data): void
    {
        $this->published[] = ['userIds' => $userIds, 'data' => $data];
    }

    /**
     * @return list<array{userIds: list<string>, data: array<string, mixed>}>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->published, static fn(array $p): bool => ($p['data']['type'] ?? null) === $type));
    }
}
