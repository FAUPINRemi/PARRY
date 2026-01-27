<?php
namespace App\Entity;

use App\Enum\RoundStatus;
use App\Repository\RoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoundRepository::class)]
#[ORM\Table(name: 'rounds')]
#[ORM\Index(columns: ['game_id'])]
class Round
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'integer')]
    private int $roundNumber;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $question = null;

    #[ORM\Column(type: 'uuid')]
    private Uuid $questionMasterId;

    #[ORM\Column(type: 'string', enumType: RoundStatus::class)]
    private RoundStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'rounds')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $questionMaster;

    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'round', cascade: ['persist', 'remove'])]
    private Collection $answers;

    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'round', cascade: ['persist', 'remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->status = RoundStatus::WAITING_QUESTION;
        $this->createdAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getRoundNumber(): int { return $this->roundNumber; }
    public function setRoundNumber(int $roundNumber): self { $this->roundNumber = $roundNumber; return $this; }
    public function getQuestion(): ?string { return $this->question; }
    public function setQuestion(?string $question): self { $this->question = $question; return $this; }
    public function getQuestionMasterId(): Uuid { return $this->questionMasterId; }
    public function setQuestionMasterId(Uuid $questionMasterId): self { $this->questionMasterId = $questionMasterId; return $this; }
    public function getStatus(): RoundStatus { return $this->status; }
    public function setStatus(RoundStatus $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getGame(): Game { return $this->game; }
    public function setGame(Game $game): self { $this->game = $game; return $this; }
    public function getQuestionMaster(): User { return $this->questionMaster; }
    public function setQuestionMaster(User $questionMaster): self { $this->questionMaster = $questionMaster; return $this; }
    public function getAnswers(): Collection { return $this->answers; }
    public function getVotes(): Collection { return $this->votes; }
}