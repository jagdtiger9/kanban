<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Doctrine;

use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Repository\CommentRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CommentRepository::class)]
final class DoctrineCommentRepository extends ServiceEntityRepository implements CommentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function save(Comment $comment): void
    {
        $em = $this->getEntityManager();
        $em->persist($comment);
        $em->flush();
    }

    public function findById(UuidInterface $id): ?Comment
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function findByCard(UuidInterface $cardId): array
    {
        return $this->findBy(['cardId' => $cardId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function remove(Comment $comment): void
    {
        $em = $this->getEntityManager();
        $em->remove($comment);
        $em->flush();
    }
}
