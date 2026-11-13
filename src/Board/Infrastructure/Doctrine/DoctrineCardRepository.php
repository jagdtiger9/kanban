<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Repository\CardRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardRepository::class)]
final class DoctrineCardRepository extends ServiceEntityRepository implements CardRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function save(Card $card): void
    {
        $em = $this->getEntityManager();
        $em->persist($card);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Card
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByColumn(UuidInterface $columnId): array
    {
        return $this->findBy(['columnId' => $columnId], ['position' => 'ASC']);
    }

    public function countByColumn(UuidInterface $columnId): int
    {
        return $this->count(['columnId' => $columnId]);
    }
}
