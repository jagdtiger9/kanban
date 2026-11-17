<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Infrastructure;

use App\Mcp\Domain\ValueObject\McpToken;
use App\Mcp\Infrastructure\File\FileMcpTokenRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class FileMcpTokenRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mcp-tokens-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testSaveCreatesDirectoryAndRoundTrips(): void
    {
        $repo = new FileMcpTokenRepository($this->dir);
        $userId = Uuid::uuid4();

        $repo->save(new McpToken($userId, 'hash-1', new \DateTimeImmutable('2027-02-15T10:00:00+00:00')));

        self::assertFileExists($this->dir . '/knb_' . $userId->toString());
        $found = $repo->findByUser($userId);
        self::assertNotNull($found);
        self::assertSame('hash-1', $found->secretHash);
        self::assertSame('2027-02-15T10:00:00+00:00', $found->expiresAt->format(\DateTimeInterface::ATOM));
    }

    public function testSaveReplacesThePreviousToken(): void
    {
        $repo = new FileMcpTokenRepository($this->dir);
        $userId = Uuid::uuid4();

        $repo->save(new McpToken($userId, 'old', new \DateTimeImmutable('2027-01-01')));
        $repo->save(new McpToken($userId, 'new', new \DateTimeImmutable('2027-01-01')));

        self::assertSame('new', $repo->findByUser($userId)?->secretHash);
        self::assertCount(1, glob($this->dir . '/*') ?: []);
    }

    public function testMissingOrCorruptFileMeansNoToken(): void
    {
        $repo = new FileMcpTokenRepository($this->dir);
        $userId = Uuid::uuid4();

        self::assertNull($repo->findByUser($userId));

        mkdir($this->dir);
        file_put_contents($this->dir . '/knb_' . $userId->toString(), 'not json');
        self::assertNull($repo->findByUser($userId));
    }
}
