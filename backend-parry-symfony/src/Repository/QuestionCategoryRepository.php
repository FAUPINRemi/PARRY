<?php

namespace App\Repository;

use App\Entity\QuestionCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class QuestionCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionCategory::class);
    }

    public function findRandomActive(): ?QuestionCategory
    {
        $categories = $this->createQueryBuilder('qc')
            ->where('qc.isActive = true')
            ->getQuery()
            ->getResult();

        if (empty($categories)) {
            return null;
        }

        return $categories[array_rand($categories)];
    }
}