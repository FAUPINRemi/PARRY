<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GameRedisService;

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

    public function createGame(bool $isPrivate = false, ?string $creatorUserId = null): Game {
        $game = new Game();
        $game->setIsPrivate($isPrivate);

        if ($isPrivate) {
            $code = $this->generateCode();
            $game->setCode($code);
        }

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $gameIdentifier = $isPrivate ? $game->getCode() : $game->getId()->toString();
        $this->gameRedisService->createGame($gameIdentifier, ['maxRounds' => 5]);

        if ($creatorUserId && $gameIdentifier) {
            $this->gameRedisService->getRedis()->setex("game:{$gameIdentifier}:creator", 86400, $creatorUserId);
        }

        return $game;
    }

    private function generateCode(): string {
        $maxTry = 100;
        for ($i = 0; $i < $maxTry; $i++) {
            $code = strtoupper(bin2hex(random_bytes(3)));
            $redis = $this->gameRedisService->getRedis();
            // setnx retourne 1 si la clé n'existait pas (code unique), 0 sinon
            $result = $redis->setnx("game:code:{$code}", '1');
            if ($result) {
                $redis->expire("game:code:{$code}", 3600);
                return $code;
            }
        }

        throw new \RuntimeException('ERREUR_GENERATION_CODE', 500);
    }

    public function joinGame(Game $game, User $user): void {
        if ($game->getStatus() !== GameStatus::WAITING) {
            throw new \RuntimeException('GAME_DEJA_COMMENCE', 400);
        }

        $playerCount = $game->getPlayers()->count();
        if ($playerCount >= self::MAX_PLAYERS) {
            throw new \RuntimeException('GAME_FULL', 403);
        }

        if ($game->getPlayers()->contains($user)) {
            throw new \RuntimeException('DEJA_REJOINT', 409);
        }

        $game->addPlayer($user);
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->addPlayer($gameIdentifier, $user->getId()->toString(), $user->getPseudo(), false);

        // Mémorise la partie active de l'utilisateur (pour reconnexion)
        $this->gameRedisService->getRedis()->setex('user:' . $user->getId()->toString() . ':activeGame', 86400, $game->getCode() ?? $game->getId()->toString());
    }

    public function debutGame(Game $game): void {
        $playerCount = $game->getPlayers()->count();

        if ($playerCount < self::MIN_PLAYERS) {
            throw new \RuntimeException('PAS_ASSEZ_DE_JOUEURS', 400);
        }

        if ($game->getStatus() !== GameStatus::WAITING) {
            throw new \RuntimeException('PARTIE_DEJA_COMMENCE', 400);
        }

        $game->setStatus(GameStatus::IN_PROGRESS);
        $game->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->startGame($gameIdentifier);
    }

    public function addAIPlayer(Game $game, User $aiUser): void {
        $game->addPlayer($aiUser);
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->addPlayer($gameIdentifier, $aiUser->getId()->toString(), $aiUser->getPseudo(), true);
    }

    public function victorireCondition(Game $game): ?string {
        $playerOK = 0;
        $iaOK = false;

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
            return 'PLAYERS_WIN';
        }

        if ($playerOK <= 2 && $iaOK) {
            return 'AI_WINS';
        }

        return null;
    }

    public function finGame(Game $game, string $winner): void {
        $game->setStatus(GameStatus::FINISHED);
        $game->setFinishedAt(new \DateTimeImmutable());

        if ($winner === 'AI_WINS') {
            $game->setWinnerType(\App\Enum\WinnerType::AI);
        } elseif ($winner === 'PLAYERS_WIN') {
            $game->setWinnerType(\App\Enum\WinnerType::PLAYERS);
        }

        $this->entityManager->flush();
    }

    public function restartGame(Game $game): void
    {
        $identifier = $game->getCode() ?? $game->getId()->toString();
        $redis = $this->gameRedisService->getRedis();

        // Reset l'entité DB
        $game->setStatus(GameStatus::WAITING);
        $game->setWinnerType(null);
        $game->setStartedAt(null);
        $game->setFinishedAt(null);

        // Suppression des rounds
        foreach ($game->getRounds() as $round) {
            $this->entityManager->remove($round);
        }

        // Suppression du joueur IA
        foreach ($game->getPlayers() as $player) {
            if (in_array('ROLE_AI', $player->getRoles())) {
                $game->removePlayer($player);
                break;
            }
        }

        $this->entityManager->flush();

        // Nettoyage Redis — état du round + flag abandon
        foreach (['round', 'round:reponses', 'round:votes', 'round:revote', 'proai', 'abandoned'] as $suffix) {
            $redis->del(["game:{$identifier}:{$suffix}"]);
        }

        // Remise en vie des joueurs humains, suppression de l'IA
        $playersRaw = $redis->hgetall("game:{$identifier}:players") ?: [];
        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            if ($p['isAI'] ?? false) {
                $redis->hdel("game:{$identifier}:players", [$playerId]);
            } else {
                $p['isAlive'] = true;
                $redis->hset("game:{$identifier}:players", $playerId, json_encode($p));
            }
        }

        // Reset statut Redis
        $redis->hset("game:{$identifier}", 'status', 'waiting');
        $redis->hset("game:{$identifier}", 'currentRound', 0);
    }

    public function deleteGame(Game $game): void
    {
        $identifier = $game->getCode() ?? $game->getId()->toString();
        $redis = $this->gameRedisService->getRedis();

        $keys = [
            "game:{$identifier}",
            "game:{$identifier}:players",
            "game:{$identifier}:round",
            "game:{$identifier}:round:reponses",
            "game:{$identifier}:round:votes",
            "game:{$identifier}:round:revote",
            "game:{$identifier}:proai",
        ];
        foreach ($keys as $key) {
            $redis->del([$key]);
        }
        if ($game->getCode()) {
            $redis->del(["game:code:{$game->getCode()}"]);
        }

        foreach ($game->getPlayers() as $player) {
            if (!in_array('ROLE_AI', $player->getRoles())) {
                $redis->del(['user:' . $player->getId()->toString() . ':activeGame']);
            }
        }

        $this->entityManager->remove($game);
        $this->entityManager->flush();
    }
}