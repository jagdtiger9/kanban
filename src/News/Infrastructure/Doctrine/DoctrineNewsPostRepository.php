<?php

declare(strict_types=1);

namespace App\News\Infrastructure\Doctrine;

use App\News\Domain\Entity\NewsPost;
use App\News\Domain\Repository\NewsPostRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(NewsPostRepository::class)]
final class DoctrineNewsPostRepository extends ServiceEntityRepository implements NewsPostRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsPost::class);
    }

    public function save(NewsPost $newsPost): void
    {
        $em = $this->getEntityManager();
        $em->persist($newsPost);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?NewsPost
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findPage(int $page, int $perPage): array
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function remove(NewsPost $newsPost): void
    {
        $em = $this->getEntityManager();
        $em->remove($newsPost);
        $em->flush();
    }
}
