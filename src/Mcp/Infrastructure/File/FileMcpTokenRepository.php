<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\File;

use App\Mcp\Domain\Repository\McpTokenRepository;
use App\Mcp\Domain\ValueObject\McpToken;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores one JSON file per user: `<dir>/knb_<userId>` = {"hash": ..., "expiresAt": ...}.
 * Files instead of a table keep the test setup migration-free; swapping to a table means
 * replacing only this class.
 */
#[AsAlias(McpTokenRepository::class)]
final class FileMcpTokenRepository implements McpTokenRepository
{
    public function __construct(
        #[Autowire('%mcp.token_directory%')]
        private string $directory,
    ) {
    }

    public function save(McpToken $token): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Cannot create MCP token directory "%s"', $this->directory));
        }

        $json = json_encode([
            'hash' => $token->secretHash,
            'expiresAt' => $token->expiresAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        // write-then-rename so a concurrent reader never sees a half-written file
        $path = $this->path($token->userId);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Cannot write MCP token file');
        }
    }

    public function findByUser(UuidInterface $userId): ?McpToken
    {
        $path = $this->path($userId);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        try {
            /** @var array{hash?: mixed, expiresAt?: mixed} $data */
            $data = json_decode($contents, true, 2, JSON_THROW_ON_ERROR);
            if (!is_string($data['hash'] ?? null) || !is_string($data['expiresAt'] ?? null)) {
                return null;
            }

            return new McpToken($userId, $data['hash'], new \DateTimeImmutable($data['expiresAt']));
        } catch (\JsonException|\Exception) {
            return null;
        }
    }

    private function path(UuidInterface $userId): string
    {
        // the id comes from a parsed UUID, so it cannot carry path separators
        return $this->directory . '/knb_' . Uuid::fromString($userId->toString())->toString();
    }
}
