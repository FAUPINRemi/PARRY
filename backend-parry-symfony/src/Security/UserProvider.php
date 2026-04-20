<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $repository = $this->entityManager->getRepository(User::class);
        
        // Recherche par email OU par pseudo
        $user = $repository->findOneBy(['email' => $identifier])
            ?? $repository->findOneBy(['pseudo' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(
                sprintf('Utilisateur avec l\'identifiant "%s" introuvable.', $identifier)
            );
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Les instances de "%s" ne sont pas supportées.', get_class($user))
            );
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(
                sprintf('Les instances de "%s" ne sont pas supportées.', get_class($user))
            );
        }

        $user->setPassword($newHashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}