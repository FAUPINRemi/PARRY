<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

  

    public function findOneByCode(string $code): ?Game
    {
        return $this->findOneBy(['code' => $code]);
    }

  

    public function findPublicWaitingGames(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.status = :status')
            ->andWhere('g.isPrivate = :isPrivate')
            ->setParameter('status', 'WAITING')
            ->setParameter('isPrivate', false)
            ->orderBy('g.createdAt', 'DESC')
            ->getResult();
    }
}