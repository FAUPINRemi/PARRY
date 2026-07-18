<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\GamePlayerAlias;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Repository\AnimalConfigRepository;
use App\Repository\GamePlayerAliasRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GameRedisService;
use App\Service\MercurePublisherService;

class GameService
{
    private const MIN_PLAYERS = 3;
    private const MAX_PLAYERS = 8;
    private const CODE_LENGTH = 6;
    private const HEARTBEAT_TIMEOUT_SECONDS = 45;
    private const MAX_SPECTATORS = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRepository $gameRepository,
        private readonly GameRedisService $gameRedisService,
        private readonly MercurePublisherService $mercurePublisher,
        private readonly AnimalConfigRepository $animalConfigRepository,
        private readonly GamePlayerAliasRepository $gamePlayerAliasRepository,
        private readonly UserRepository $userRepository,
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
            $this->joinAsSpectator($game, $user);
            return;
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

        $playerAlias = $this->assignRandomAlias($game, $user);
        $animalConfig = $playerAlias->getAnimalConfig();

        $this->gameRedisService->addPlayer(
            $gameIdentifier,
            $user->getId()->toString(),
            $user->getPseudo(),
            false,
            $animalConfig->getAlias(),
            $animalConfig->getResponseSprite(),
            $animalConfig->getQuestionSprite(),
            $animalConfig->getEliminationSprite()
        );

        // Mémorise la partie active de l'utilisateur (pour reconnexion)
        $this->gameRedisService->getRedis()->setex('user:' . $user->getId()->toString() . ':activeGame', 86400, $gameIdentifier);
    }

    // Rejoint une partie déjà lancée en tant que spectateur (lecture seule, promu joueur au prochain restart)
    public function joinAsSpectator(Game $game, User $user): void
    {
        $identifier = $game->getCode() ?? $game->getId()->toString();
        $redis = $this->gameRedisService->getRedis();
        $userId = $user->getId()->toString();

        if ($game->getPlayers()->contains($user)) {
            throw new \RuntimeException('DEJA_REJOINT', 409);
        }

        if ($redis->hexists("game:{$identifier}:spectators", $userId)) {
            throw new \RuntimeException('DEJA_SPECTATEUR', 409);
        }

        if ($redis->hlen("game:{$identifier}:spectators") >= self::MAX_SPECTATORS) {
            throw new \RuntimeException('SPECTATEURS_COMPLET', 403);
        }

        $redis->hset("game:{$identifier}:spectators", $userId, json_encode([
            'nickname' => $user->getPseudo(),
            'joinedAt' => time(),
        ]));

        $redis->setex('user:' . $userId . ':activeGame', 86400, $identifier);

        $this->mercurePublisher->publish("/game/{$identifier}", ['event' => 'spectator_joined']);
    }

    public function debutGame(Game $game): void {
        $humanCount = 0;
        foreach ($game->getPlayers() as $player) {
            if (!in_array('ROLE_AI', $player->getRoles())) {
                $humanCount++;
            }
        }

        if ($humanCount < self::MIN_PLAYERS) {
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

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'game_started']);
    }

    public function addAIPlayer(Game $game, User $aiUser): void {
        $game->addPlayer($aiUser);
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $playerAlias = $this->assignRandomAlias($game, $aiUser);
        $animalConfig = $playerAlias->getAnimalConfig();

        $this->gameRedisService->addPlayer(
            $gameIdentifier,
            $aiUser->getId()->toString(),
            $aiUser->getPseudo(),
            true,
            $animalConfig->getAlias(),
            $animalConfig->getResponseSprite(),
            $animalConfig->getQuestionSprite(),
            $animalConfig->getEliminationSprite()
        );
    }

    public function victorireCondition(Game $game): ?string {
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $proAiId = $this->gameRedisService->getRedis()->get("game:{$gameIdentifier}:proai");

        if ($proAiId !== null) {
            $eliminatedRounds = array_values(array_filter(
                $game->getRounds()->toArray(),
                fn($round) => $round->getEliminatedPlayer() !== null
            ));

            if (count($eliminatedRounds) === 1 && $eliminatedRounds[0]->getEliminatedPlayer()->getId()->toString() === $proAiId) {
                return 'PRO_IA_WINS';
            }
        }

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
        } elseif ($winner === 'PRO_IA_WINS') {
            $game->setWinnerType(\App\Enum\WinnerType::PRO_IA);
        }

        $this->entityManager->flush();
    }

    public function deleteGame(Game $game): void {
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        // Publier l'événement avant de supprimer les données
        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'game_deleted']);

        // Supprimer les clés Redis
        $redis = $this->gameRedisService->getRedis();
        $spectatorsRaw = $redis->hgetall("game:{$gameIdentifier}:spectators") ?: [];

        $keys = [
            "game:{$gameIdentifier}",
            "game:{$gameIdentifier}:players",
            "game:{$gameIdentifier}:spectators",
            "game:{$gameIdentifier}:round",
            "game:{$gameIdentifier}:round:reponses",
            "game:{$gameIdentifier}:round:votes",
            "game:{$gameIdentifier}:round:revote",
            "game:{$gameIdentifier}:proai",
            "game:{$gameIdentifier}:creator",
        ];
        foreach ($keys as $key) {
            $redis->del([$key]);
        }

        if ($game->getCode()) {
            $redis->del(["game:code:{$game->getCode()}"]);
        }

        // Nettoyage des références utilisateurs (activeGame)
        foreach ($game->getPlayers() as $player) {
            if (!in_array('ROLE_AI', $player->getRoles())) {
                $redis->del(['user:' . $player->getId()->toString() . ':activeGame']);
            }
        }

        foreach (array_keys($spectatorsRaw) as $spectatorId) {
            $redis->del(['user:' . $spectatorId . ':activeGame']);
        }

        // Supprimer l'entité en base de données
        $this->entityManager->remove($game);
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

        // Nettoyage Redis — état du round +  abandon
        foreach (['round', 'round:reponses', 'round:votes', 'round:revote', 'proai', 'abandoned'] as $suffix) {
            $redis->del(["game:{$identifier}:{$suffix}"]);
        }

        // Remise en vie des joueurs humains, suppression de l'IA
        $playersRaw = $redis->hgetall("game:{$identifier}:players") ?: [];
        $activeHumanCount = 0;
        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            if ($p['isAI'] ?? false) {
                $redis->hdel("game:{$identifier}:players", [$playerId]);
            } else {
                $p['isAlive'] = true;
                $redis->hset("game:{$identifier}:players", $playerId, json_encode($p));
                $activeHumanCount++;
            }
        }

        // Promotion des spectateurs en joueurs actifs, par ordre d'arrivée, dans la limite de MAX_PLAYERS
        $spectatorsRaw = $redis->hgetall("game:{$identifier}:spectators") ?: [];
        $spectatorEntries = [];
        foreach ($spectatorsRaw as $spectatorId => $spectatorJson) {
            $s = json_decode($spectatorJson, true);
            $spectatorEntries[] = ['id' => $spectatorId, 'joinedAt' => $s['joinedAt'] ?? 0];
        }
        usort($spectatorEntries, fn($a, $b) => $a['joinedAt'] <=> $b['joinedAt']);

        $slotsAvailable = self::MAX_PLAYERS - $activeHumanCount;
        foreach ($spectatorEntries as $entry) {
            if ($slotsAvailable <= 0) {
                break;
            }

            $spectatorUser = $this->userRepository->find($entry['id']);
            if (!$spectatorUser) {
                $redis->hdel("game:{$identifier}:spectators", [$entry['id']]);
                continue;
            }

            $game->addPlayer($spectatorUser);
            $this->entityManager->flush();

            $playerAlias = $this->assignRandomAlias($game, $spectatorUser);
            $animalConfig = $playerAlias->getAnimalConfig();

            $this->gameRedisService->addPlayer(
                $identifier,
                $entry['id'],
                $spectatorUser->getPseudo(),
                false,
                $animalConfig->getAlias(),
                $animalConfig->getResponseSprite(),
                $animalConfig->getQuestionSprite(),
                $animalConfig->getEliminationSprite()
            );

            $redis->hdel("game:{$identifier}:spectators", [$entry['id']]);
            $redis->setex('user:' . $entry['id'] . ':activeGame', 86400, $identifier);

            $slotsAvailable--;
        }

        // Reset statut Redis
        $redis->hset("game:{$identifier}", 'status', 'waiting');
        $redis->hset("game:{$identifier}", 'currentRound', 0);

        $this->mercurePublisher->publish("/game/{$identifier}", ['event' => 'game_restarted']);
    }

    // Met à jour l'horodatage de dernière activité d'un joueur ou spectateur (appelé à chaque poll d'état)
    public function touchPlayerHeartbeat(Game $game, string $userId): void
    {
        $identifier = $game->getCode() ?? $game->getId()->toString();
        $redis = $this->gameRedisService->getRedis();

        $raw = $redis->hget("game:{$identifier}:players", $userId);
        if ($raw) {
            $data = json_decode($raw, true);
            $data['lastSeen'] = time();
            $redis->hset("game:{$identifier}:players", $userId, json_encode($data));
            return;
        }

        $specRaw = $redis->hget("game:{$identifier}:spectators", $userId);
        if ($specRaw) {
            $data = json_decode($specRaw, true);
            $data['lastSeen'] = time();
            $redis->hset("game:{$identifier}:spectators", $userId, json_encode($data));
        }
    }

    // Retire les joueurs humains n'ayant plus donné signe de vie depuis HEARTBEAT_TIMEOUT_SECONDS.
    // Si le créateur est concerné, la partie est arrêtée pour tout le monde. Retourne true si la partie a été supprimée.
    public function pruneDisconnectedPlayers(Game $game): bool
    {
        if ($game->getStatus() === GameStatus::FINISHED) {
            return false;
        }

        $identifier = $game->getCode() ?? $game->getId()->toString();
        $redis = $this->gameRedisService->getRedis();
        $playersRaw = $redis->hgetall("game:{$identifier}:players") ?: [];
        $now = time();

        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            if ($p['isAI'] ?? false) {
                continue;
            }

            $lastSeen = $p['lastSeen'] ?? null;
            if ($lastSeen === null || ($now - $lastSeen) < self::HEARTBEAT_TIMEOUT_SECONDS) {
                continue;
            }

            $creatorId = $redis->get("game:{$identifier}:creator");
            if ($creatorId !== null && $creatorId === $playerId) {
                $this->deleteGame($game);
                return true;
            }

            $playerEntity = null;
            foreach ($game->getPlayers() as $candidate) {
                if ($candidate->getId()->toString() === $playerId) {
                    $playerEntity = $candidate;
                    break;
                }
            }

            if ($playerEntity) {
                $game->removePlayer($playerEntity);
                $this->entityManager->flush();
            }

            $redis->hdel("game:{$identifier}:players", [$playerId]);
            $redis->del(['user:' . $playerId . ':activeGame']);

            $this->mercurePublisher->publish("/game/{$identifier}", ['event' => 'player_disconnected']);
        }

        // Spectateurs inactifs : retrait silencieux, aucune incidence sur la partie en cours
        $spectatorsRaw = $redis->hgetall("game:{$identifier}:spectators") ?: [];
        foreach ($spectatorsRaw as $spectatorId => $spectatorJson) {
            $s = json_decode($spectatorJson, true);
            $lastSeen = $s['lastSeen'] ?? null;
            if ($lastSeen === null || ($now - $lastSeen) < self::HEARTBEAT_TIMEOUT_SECONDS) {
                continue;
            }

            $redis->hdel("game:{$identifier}:spectators", [$spectatorId]);
            $redis->del(['user:' . $spectatorId . ':activeGame']);
        }

        return false;
    }

    private function assignRandomAlias(Game $game, User $user): GamePlayerAlias
    {
        $existing = $this->gamePlayerAliasRepository->findByGameAndPlayer($game, $user);
        if ($existing) {
            return $existing;
        }

        $allAnimals = $this->animalConfigRepository->findAll();
        $usedAliases = $this->gamePlayerAliasRepository->findUsedAliasesInGame($game);

        $usedAnimalIds = array_map(fn($a) => $a->getAnimalConfig()->getId(), $usedAliases);
        $availableAnimals = array_filter($allAnimals, fn($animal) => !in_array($animal->getId(), $usedAnimalIds));

        if (empty($availableAnimals)) {
            throw new \RuntimeException('PAS_D_ALIAS_DISPONIBLE', 400);
        }

        $randomAnimal = $availableAnimals[array_rand($availableAnimals)];

        $playerAlias = new GamePlayerAlias();
        $playerAlias->setGame($game);
        $playerAlias->setPlayer($user);
        $playerAlias->setAnimalConfig($randomAnimal);

        $this->entityManager->persist($playerAlias);
        $this->entityManager->flush();

        return $playerAlias;
    }
}