<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Repository\BoardRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(BoardRepository::class)]
final class DoctrineBoardRepository extends ServiceEntityRepository implements BoardRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Board::class);
    }

    public function save(Board $board): void
    {
        $em = $this->getEntityManager();
        $em->persist($board);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Board
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByProject(UuidInterface $projectId): ?Board
    {
        return $this->findOneBy(['projectId' => $projectId]);
    }
}
