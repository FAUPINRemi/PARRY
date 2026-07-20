<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GamePlayerAlias;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class GamePlayerAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GamePlayerAlias::class);
    }

    public function findByGameAndPlayer(Game $game, User $player): ?GamePlayerAlias
    {
        return $this->findOneBy([
            'game' => $game,
            'player' => $player,
        ]);
    }

    public function findUsedAliasesInGame(Game $game): array
    {
        return $this->findBy(['game' => $game]);
    }

    public function countAliasesInGame(Game $game): int
    {
        return $this->count(['game' => $game]);
    }
}
