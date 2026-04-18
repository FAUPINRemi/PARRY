<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudo est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email value n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le pseudo est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le pseudo doit contenir au moins limit caractères.',
        maxMessage: 'Le pseudo ne peut pas dépasser limit caractères.'
    )]
    private ?string $pseudo = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    // --- AJOUT : avatar en base64 + mime ---
    // On stocke UNIQUEMENT le base64 brut (sans "data:image/png;base64,").
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $avatarBase64 = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $avatarMime = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): self
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

   
    public function getAvatarBase64(): ?string
    {
        return $this->avatarBase64;
    }

    public function setAvatarBase64(?string $avatarBase64): self
    {
        $this->avatarBase64 = $avatarBase64;
        return $this;
    }

    public function getAvatarMime(): ?string
    {
        return $this->avatarMime;
    }

    public function setAvatarMime(?string $avatarMime): self
    {
        $this->avatarMime = $avatarMime;
        return $this;
    }

  
    public function getAvatarDataUrl(): ?string
    {
        if (!$this->avatarBase64) {
            return null;
        }
        $mime = $this->avatarMime ?: 'image/png';
        return 'data:' . $mime . ';base64,' . $this->avatarBase64;
    }
}