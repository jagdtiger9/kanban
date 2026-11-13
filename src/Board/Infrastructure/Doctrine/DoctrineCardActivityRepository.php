<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\CardActivity;
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
}
