<?php

namespace App\Repository;

use App\Entity\Round;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class RoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Round::class);
    }

   
    public function findLatestByGame($game): ?Round
    {
        return $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.roundNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    
    public function findByGame($game): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.roundNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}