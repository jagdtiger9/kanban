<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\CardOccurrence;
use App\Board\Domain\Repository\CardOccurrenceRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardOccurrenceRepository::class)]
final class DoctrineCardOccurrenceRepository extends ServiceEntityRepository implements CardOccurrenceRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardOccurrence::class);
    }

    public function save(CardOccurrence $occurrence): void
    {
        $em = $this->getEntityManager();
        $em->persist($occurrence);
        $em->flush();
    }

    public function remove(CardOccurrence $occurrence): void
    {
        $em = $this->getEntityManager();
        $em->remove($occurrence);
        $em->flush();
    }

    public function findByCardAndPeriod(UuidInterface $cardId, \DateTimeImmutable $period): ?CardOccurrence
    {
        return $this->findOneBy(['cardId' => $cardId, 'period' => $period]);
    }

    public function findCompletedPeriodsByCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('o.cardId AS cardId', 'o.period AS period')
            ->where('o.cardId IN (:cardIds)')
            ->setParameter('cardIds', $cardIds)
            ->orderBy('o.period', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $periods = [];
        foreach ($rows as $row) {
            $periods[(string)$row['cardId']][] = $row['period']->format('Y-m-d');
        }

        return $periods;
    }
}
