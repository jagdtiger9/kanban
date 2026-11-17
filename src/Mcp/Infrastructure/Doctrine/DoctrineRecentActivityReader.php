<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Column;
use App\Mcp\Domain\ReadModel\RecentActivity;
use App\Mcp\Domain\Repository\RecentActivityReader;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Temporary: reads Board/Project tables directly from the Mcp module. After MCP testing the
 * query moves to the Board module and this class goes away (docs/plans/mcp.md).
 */
#[AsAlias(RecentActivityReader::class)]
final class DoctrineRecentActivityReader implements RecentActivityReader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findSince(\DateTimeImmutable $since, ?UuidInterface $memberId, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'a.type AS type',
                'a.payload AS payload',
                'a.createdAt AS createdAt',
                'c.id AS cardId',
                'c.title AS cardTitle',
                'p.id AS projectId',
                'p.name AS projectName',
                'u.displayName AS actorName',
            )
            ->from(CardActivity::class, 'a')
            ->join(Card::class, 'c', 'WITH', 'c.id = a.cardId')
            ->join(Column::class, 'col', 'WITH', 'col.id = c.columnId')
            ->join(Board::class, 'b', 'WITH', 'b.id = col.boardId')
            ->join(Project::class, 'p', 'WITH', 'p.id = b.projectId')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = a.actorId')
            ->where('a.createdAt > :since')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('since', $since, 'datetimetz_micro')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        if ($memberId !== null) {
            $qb->andWhere(sprintf(
                'EXISTS (SELECT m.id FROM %s m WHERE m.projectId = p.id AND m.userId = :memberId)',
                ProjectMember::class,
            ))->setParameter('memberId', $memberId, 'uuid');
        }

        return array_map(
            static fn(array $row): RecentActivity => new RecentActivity(
                self::uuid($row['projectId']),
                (string)$row['projectName'],
                self::uuid($row['cardId']),
                (string)$row['cardTitle'],
                $row['actorName'] !== null ? (string)$row['actorName'] : null,
                (string)$row['type'],
                self::payload($row['payload']),
                self::dateTime($row['createdAt']),
            ),
            $qb->getQuery()->getArrayResult(),
        );
    }

    private static function uuid(mixed $value): UuidInterface
    {
        return $value instanceof UuidInterface ? $value : Uuid::fromString((string)$value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return is_array($value) ? $value : [];
    }

    private static function dateTime(mixed $value): \DateTimeImmutable
    {
        return $value instanceof \DateTimeImmutable ? $value : new \DateTimeImmutable((string)$value);
    }
}
