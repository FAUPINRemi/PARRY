<?php
namespace App\Entity;

use App\Enum\ModerationStatus;
use App\Repository\AnswerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ORM\Table(name: 'answers')]
#[ORM\Index(columns: ['round_id'])]
class Answer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'boolean')]
    private bool $isFromAI;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $authorId = null;

    #[ORM\Column(type: 'string', enumType: ModerationStatus::class)]
    private ModerationStatus $moderationStatus;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    private Round $round;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $author = null;

    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'answer', cascade: ['persist', 'remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->moderationStatus = ModerationStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
        $this->votes = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function isFromAI(): bool { return $this->isFromAI; }
    public function setIsFromAI(bool $isFromAI): self { $this->isFromAI = $isFromAI; return $this; }
    public function getAuthorId(): ?Uuid { return $this->authorId; }
    public function setAuthorId(?Uuid $authorId): self { $this->authorId = $authorId; return $this; }
    public function getModerationStatus(): ModerationStatus { return $this->moderationStatus; }
    public function setModerationStatus(ModerationStatus $moderationStatus): self { $this->moderationStatus = $moderationStatus; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRound(): Round { return $this->round; }
    public function setRound(Round $round): self { $this->round = $round; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }
    public function getVotes(): Collection { return $this->votes; }
}