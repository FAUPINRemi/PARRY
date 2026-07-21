<?php

namespace App\Repository;

use App\Entity\AnimalConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnimalConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalConfig::class);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }

    public function findByAlias(string $alias): ?AnimalConfig
    {
        return $this->findOneBy(['alias' => $alias]);
    }
}
