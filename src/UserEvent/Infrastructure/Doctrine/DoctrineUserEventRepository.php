<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Doctrine;

use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserEventRepository::class)]
final class DoctrineUserEventRepository extends ServiceEntityRepository implements UserEventRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEvent::class);
    }

    public function save(UserEvent $event): void
    {
        $em = $this->getEntityManager();
        $em->persist($event);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?UserEvent
    {
        return $this->find($id);
    }

    public function existsByNaturalKey(
        UuidInterface $userId,
        UserEventType $type,
        UuidInterface $aggregateRootId,
        \DateTimeImmutable $occurredOn,
    ): bool {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.userId = :userId')
            ->andWhere('e.type = :type')
            ->andWhere('e.aggregateRootId = :aggregateRootId')
            ->andWhere('e.occurredOn = :occurredOn')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type->value)
            ->setParameter('aggregateRootId', $aggregateRootId)
            ->setParameter('occurredOn', $occurredOn, 'datetimetz_micro')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function findPageForUser(UuidInterface $userId, ?UserEventCursor $cursor, int $limit): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('e.occurredOn', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit + 1);

        if ($cursor !== null) {
            $qb->andWhere('e.occurredOn < :occurredOn OR (e.occurredOn = :occurredOn AND e.id < :cursorId)')
                ->setParameter('occurredOn', $cursor->occurredOn(), 'datetimetz_micro')
                ->setParameter('cursorId', $cursor->id());
        }

        /** @var list<UserEvent> */
        return $qb->getQuery()->getResult();
    }

    public function unreadCount(UuidInterface $userId): int
    {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.userId = :userId')
            ->andWhere('e.seenAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function markAllSeen(UuidInterface $userId, \DateTimeImmutable $seenAt): void
    {
        $this->getEntityManager()->createQuery(
            'UPDATE ' . UserEvent::class . ' e SET e.seenAt = :seenAt WHERE e.userId = :userId AND e.seenAt IS NULL',
        )
            ->setParameter('seenAt', $seenAt, 'datetimetz_micro')
            ->setParameter('userId', $userId)
            ->execute();
    }
}
