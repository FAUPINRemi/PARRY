<?php

namespace App\Entity;

use App\Repository\AnimalConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnimalConfigRepository::class)]
#[ORM\Table(name: 'animal_configs')]
class AnimalConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $alias;

    #[ORM\Column(type: 'string', length: 100)]
    private string $animalName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $responseSprite;

    #[ORM\Column(type: 'string', length: 255)]
    private string $questionSprite;

    #[ORM\Column(type: 'string', length: 255)]
    private string $eliminationSprite;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $soundEffects = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $particles = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    public function getAnimalName(): string
    {
        return $this->animalName;
    }

    public function setAnimalName(string $animalName): self
    {
        $this->animalName = $animalName;
        return $this;
    }

    public function getResponseSprite(): string
    {
        return $this->responseSprite;
    }

    public function setResponseSprite(string $responseSprite): self
    {
        $this->responseSprite = $responseSprite;
        return $this;
    }

    public function getQuestionSprite(): string
    {
        return $this->questionSprite;
    }

    public function setQuestionSprite(string $questionSprite): self
    {
        $this->questionSprite = $questionSprite;
        return $this;
    }

    public function getEliminationSprite(): string
    {
        return $this->eliminationSprite;
    }

    public function setEliminationSprite(string $eliminationSprite): self
    {
        $this->eliminationSprite = $eliminationSprite;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getSoundEffects(): ?array
    {
        return $this->soundEffects;
    }

    public function setSoundEffects(?array $soundEffects): self
    {
        $this->soundEffects = $soundEffects;
        return $this;
    }

    public function getParticles(): ?string
    {
        return $this->particles;
    }

    public function setParticles(?string $particles): self
    {
        $this->particles = $particles;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
