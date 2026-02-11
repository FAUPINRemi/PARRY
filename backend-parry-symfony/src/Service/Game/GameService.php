<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;

class GameService
{
    private const MIN_PLAYERS = 3;
    private const MAX_PLAYERS = 8;
    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly GameRedisService $gameRedisService
    ) {}

    //Creation d'une partie 
    public function createGame(bool $isPrivate = false): Game {
        $game = new Game();
        $game->setIsPrivate($isPrivate);

        //Si la partie est privée alors on génère un code d'accès
        if ($isPrivate) {
            $code = $this->generateCode();
            $game->setCode($code);
        }

        // Persistance de la partie en bdd
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        // Initialisation de la partie dans Redis
        $gameIdentifier = $isPrivate ? $game->getCode() : $game->getId()->toString();
        $this->gameRedisService->createGame($gameIdentifier, ['maxround' => 5]);

        return $game;
    }

    // Génération du code pour les parties (utilisation de redis pour garder les code et eviter les doublons)
    private function generateCode(): string {
        $maxTry = 100;
        for ($i = 0; $i < $maxTry; $i++) {
            $code = strtoupper(bin2hex(random_bytes(3)));
            $result = $this->gameRedisService->getRedis()->set("game:code:{$code}", 1, ['nx', 'ex' => 3600]);
            if ($result) {
                return $code;
            }
        }
        
        throw new \RuntimeException('ERREUR_GENERATION_CODE', 500);
    }

    // Rejoindre une partie 
    public function joinGame(Game $game, User $user): void {
        if ($game->getStatus() !== GameStatus::WAITING) {
            throw new \RuntimeException('GAME_DEJA_COMMENCE', 400);
        }

        // Vérification du nombre de joueurs
        $playerCount = $game->getPlayers()->count();
        if ($playerCount >= self::MAX_PLAYERS) {
            throw new \RuntimeException('GAME_FULL', 403);
        }

        if ($game->getPlayers()->contains($user)) {
            throw new \RuntimeException('DEJA_REJOINT', 409);
        }

        // Ajout du joueur à la partie
        $game->addPlayer($user);
        $this->entityManager->flush();

        // Mise à jour de Redis
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->addPlayer($gameIdentifier, $user->getId()->toString(), $user->getPseudo(), false);
    }

    // Démarrer une partie
    public function debutGame(Game $game): void {
        $playerCount = $game->getPlayers()->count();
        
        if ($playerCount < self::MIN_PLAYERS) {
            throw new \RuntimeException('PAS_ASSEZ_DE_JOUEURS', 400);
        }
        
        if ($game->getStatus() !== GameStatus::WAITING) {
            throw new \RuntimeException('PARTIE_DEJA_COMMENCE', 400);
        }
        
        // Mise à jour du statut de la partie en bdd
        $game->setStatus(GameStatus::IN_PROGRESS);
        $game->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Mise à jour de Redis
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->startGame($gameIdentifier);
    }

    public function addAIPlayer(Game $game, User $aiUser): void {
        $game->addPlayer($aiUser);
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->addPlayer($gameIdentifier, $aiUser->getId()->toString(), $aiUser->getPseudo(), true);
    }

    // Condition de victoire
    public function victorireCondition(Game $game): ?string {
        $playerOK = 0;
        $iaOK = false;

        // On vérifie dans Redis le statut de chaque joueur (vivant ou éliminé)
        foreach ($game->getPlayers() as $player) {
            $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
            $playerData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $player->getId()->toString());

            if ($playerData) {
                $data = json_decode($playerData, true);
                if ($data['isAlive']) {
                    $playerOK++;
                    if ($data['isAI'] ?? false) {
                        $iaOK = true;
                    }
                }
            }
        }

        if (!$iaOK) {
            return 'VICTOIRE_JOUEURS';
        }

        if ($playerOK <= 2 && $iaOK) {
            return 'VICTOIRE_IA';
        }

        return null;
    }

    // Fin de partie
    public function finGame(Game $game, string $winner): void {
        $game->setStatus(GameStatus::FINISHED);
        $game->setFinishedAt(new \DateTimeImmutable());
        
        if ($winner === 'AI_WINS') {
            $game->setWinnerType(\App\Enum\WinnerType::AI);
        } else {
            $game->setWinnerType(\App\Enum\WinnerType::PLAYERS);
        }

        $this->entityManager->flush();
    }
}