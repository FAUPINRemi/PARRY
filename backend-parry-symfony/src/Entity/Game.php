<?php
namespace App\Entity;

use App\Enum\GameStatus;
use App\Enum\WinnerType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'games')]
#[ORM\Index(columns: ['code'])]
#[ORM\Index(columns: ['status'])]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 6, unique: true, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', enumType: GameStatus::class)]
    private GameStatus $status;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrivate;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $winnerId = null;

    #[ORM\Column(type: 'string', enumType: WinnerType::class, nullable: true)]
    private ?WinnerType $winnerType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'games')]
    #[ORM\JoinTable(name: 'game_players')]
    private Collection $players;

    #[ORM\OneToMany(targetEntity: Round::class, mappedBy: 'game', cascade: ['persist', 'remove'])]
    private Collection $rounds;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->status = GameStatus::WAITING;
        $this->createdAt = new \DateTimeImmutable();
        $this->players = new ArrayCollection();
        $this->rounds = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): self { $this->code = $code; return $this; }
    public function getStatus(): GameStatus { return $this->status; }
    public function setStatus(GameStatus $status): self { $this->status = $status; return $this; }
    public function isPrivate(): bool { return $this->isPrivate; }
    public function setIsPrivate(bool $isPrivate): self { $this->isPrivate = $isPrivate; return $this; }
    public function getWinnerId(): ?Uuid { return $this->winnerId; }
    public function setWinnerId(?Uuid $winnerId): self { $this->winnerId = $winnerId; return $this; }
    public function getWinnerType(): ?WinnerType { return $this->winnerType; }
    public function setWinnerType(?WinnerType $winnerType): self { $this->winnerType = $winnerType; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $startedAt): self { $this->startedAt = $startedAt; return $this; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self { $this->finishedAt = $finishedAt; return $this; }
    public function getPlayers(): Collection { return $this->players; }
    public function addPlayer(User $player): self { 
        if (!$this->players->contains($player)) { 
            $this->players[] = $player; 
        } 
        return $this; 
    }
    public function removePlayer(User $player): self { 
        $this->players->removeElement($player); 
        return $this; 
    }
    public function getRounds(): Collection { return $this->rounds; }
}