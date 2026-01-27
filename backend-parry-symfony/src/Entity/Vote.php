<?php
namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'votes')]
class Vote
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $voterId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $answerId;

    #[ORM\Column(type: 'boolean')]
    private bool $votedAsAI;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private User $voter;

    #[ORM\ManyToOne(targetEntity: Answer::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private Answer $answer;

    #[ORM\ManyToOne(targetEntity: Round::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private Round $round;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getVoterId(): Uuid { return $this->voterId; }
    public function setVoterId(Uuid $voterId): self { $this->voterId = $voterId; return $this; }
    public function getAnswerId(): Uuid { return $this->answerId; }
    public function setAnswerId(Uuid $answerId): self { $this->answerId = $answerId; return $this; }
    public function isVotedAsAI(): bool { return $this->votedAsAI; }
    public function setVotedAsAI(bool $votedAsAI): self { $this->votedAsAI = $votedAsAI; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getVoter(): User { return $this->voter; }
    public function setVoter(User $voter): self { $this->voter = $voter; return $this; }
    public function getAnswer(): Answer { return $this->answer; }
    public function setAnswer(Answer $answer): self { $this->answer = $answer; return $this; }
    public function getRound(): Round { return $this->round; }
    public function setRound(Round $round): self { $this->round = $round; return $this; }
}