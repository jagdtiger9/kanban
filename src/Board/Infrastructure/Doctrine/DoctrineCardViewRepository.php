<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Repository\CardViewRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CardViewRepository::class)]
final class DoctrineCardViewRepository extends ServiceEntityRepository implements CardViewRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardView::class);
    }

    public function save(CardView $view): void
    {
        $em = $this->getEntityManager();
        $em->persist($view);
        $em->flush();
    }

    public function findByUserAndCard(UuidInterface $userId, UuidInterface $cardId): ?CardView
    {
        return $this->findOneBy(['userId' => $userId, 'cardId' => $cardId]);
    }
}
