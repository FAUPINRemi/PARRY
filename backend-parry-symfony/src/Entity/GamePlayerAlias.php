<?php

namespace App\Entity;

use App\Repository\GamePlayerAliasRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GamePlayerAliasRepository::class)]
#[ORM\Table(name: 'game_player_aliases')]
#[ORM\UniqueConstraint(columns: ['game_id', 'player_id'])]
#[ORM\UniqueConstraint(columns: ['game_id', 'animal_config_id'])]
class GamePlayerAlias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'playerAliases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $player;

    #[ORM\ManyToOne(targetEntity: AnimalConfig::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private AnimalConfig $animalConfig;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $assignedAt;

    public function __construct()
    {
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): self
    {
        $this->game = $game;
        return $this;
    }

    public function getPlayer(): User
    {
        return $this->player;
    }

    public function setPlayer(User $player): self
    {
        $this->player = $player;
        return $this;
    }

    public function getAnimalConfig(): AnimalConfig
    {
        return $this->animalConfig;
    }

    public function setAnimalConfig(AnimalConfig $animalConfig): self
    {
        $this->animalConfig = $animalConfig;
        return $this;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
