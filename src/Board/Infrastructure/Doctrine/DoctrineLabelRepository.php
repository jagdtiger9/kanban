<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\LabelRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LabelRepository::class)]
final class DoctrineLabelRepository extends ServiceEntityRepository implements LabelRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Label::class);
    }

    public function save(Label $label): void
    {
        $em = $this->getEntityManager();
        $em->persist($label);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Label
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function remove(Label $label): void
    {
        $em = $this->getEntityManager();
        $em->remove($label);
        $em->flush();
    }

    public function findByBoard(UuidInterface $boardId): array
    {
        return $this->findBy(['boardId' => $boardId], ['createdAt' => 'ASC']);
    }
}
