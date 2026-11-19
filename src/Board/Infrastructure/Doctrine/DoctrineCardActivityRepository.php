<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\CardActivityRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardActivityRepository::class)]
final class DoctrineCardActivityRepository extends ServiceEntityRepository implements CardActivityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardActivity::class);
    }

    public function save(CardActivity $activity): void
    {
        $em = $this->getEntityManager();
        $em->persist($activity);
        $em->flush();
    }

    public function findByCard(UuidInterface $cardId): array
    {
        return $this->findBy(['cardId' => $cardId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function countUnseenByBoard(UuidInterface $boardId, UuidInterface $userId, \DateTimeImmutable $baseline): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.cardId AS cardId', 'COUNT(a.id) AS unseenCount')
            ->join(Card::class, 'c', 'WITH', 'c.id = a.cardId')
            ->join(Column::class, 'col', 'WITH', 'col.id = c.columnId')
            ->leftJoin(CardView::class, 'cv', 'WITH', 'cv.cardId = a.cardId AND cv.userId = :userId')
            ->where('col.boardId = :boardId')
            ->andWhere('a.actorId <> :userId')
            // Two explicit floors reproduce GREATEST(cv.lastSeenAt, baseline):
            // DQL has no GREATEST function.
            ->andWhere('a.createdAt > COALESCE(cv.lastSeenAt, :baseline)')
            ->andWhere('a.createdAt > :baseline')
            ->setParameter('boardId', $boardId, 'uuid')
            ->setParameter('userId', $userId, 'uuid')
            ->setParameter('baseline', $baseline, 'datetimetz_micro')
            ->groupBy('a.cardId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['cardId']] = (int)$row['unseenCount'];
        }

        return $counts;
    }
}
