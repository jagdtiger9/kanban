<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\ColumnRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ColumnRepository::class)]
final class DoctrineColumnRepository extends ServiceEntityRepository implements ColumnRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Column::class);
    }

    public function save(Column $column): void
    {
        $em = $this->getEntityManager();
        $em->persist($column);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Column
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByBoard(UuidInterface $boardId): array
    {
        return $this->findBy(['boardId' => $boardId], ['position' => 'ASC']);
    }

    public function remove(Column $column): void
    {
        $em = $this->getEntityManager();
        $em->remove($column);
        $em->flush();
    }
}
