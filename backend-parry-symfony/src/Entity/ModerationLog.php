<?php
namespace App\Entity;

use App\Repository\ModerationLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ModerationLogRepository::class)]
#[ORM\Table(name: 'moderation_logs')]
class ModerationLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'string', length: 255)]
    private string $flaggedReason;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $gameId = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $timestamp;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Game $game = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->timestamp = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function getFlaggedReason(): string { return $this->flaggedReason; }
    public function setFlaggedReason(string $flaggedReason): self { $this->flaggedReason = $flaggedReason; return $this; }
    public function getGameId(): ?Uuid { return $this->gameId; }
    public function setGameId(?Uuid $gameId): self { $this->gameId = $gameId; return $this; }
    public function getUserId(): ?Uuid { return $this->userId; }
    public function setUserId(?Uuid $userId): self { $this->userId = $userId; return $this; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
    public function getGame(): ?Game { return $this->game; }
    public function setGame(?Game $game): self { $this->game = $game; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
}