<?php
namespace App\Controller\Api;

use App\Entity\Game;
use App\Entity\User;
use App\Entity\GamePlayerAlias;
use App\Enum\GameStatus;
use App\Repository\AnimalConfigRepository;
use App\Repository\GameRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\AI\BudgetGuard;
use App\Service\AI\VertexAiClient;
use App\Service\Game\GameService;
use App\Service\GameRedisService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/game')]
class GameController extends AbstractController
{
    public function __construct(
        private readonly GameService $gameService,
        private readonly GameRepository $gameRepository,
        private readonly RoundRepository $roundRepository,
        private readonly UserRepository $userRepository,
        private readonly AnimalConfigRepository $animalConfigRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GameRedisService $gameRedisService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly BudgetGuard $budgetGuard,
        private readonly VertexAiClient $vertexAiClient
    ) {}

    #[Route('/create', name: 'api_game_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/game/create',
        summary: 'Créer une nouvelle partie',
        tags: ['Game']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'isPrivate', type: 'boolean', example: false, description: 'Partie privée ou publique')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Partie créée avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'game',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'code', type: 'string', example: 'ABC123'),
                        new OA\Property(property: 'isPrivate', type: 'boolean', example: false),
                        new OA\Property(property: 'status', type: 'string', example: 'waiting')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Erreur lors de la création')]
    public function createGame(Request $request): JsonResponse {
        /** @var \App\Entity\User|null $creator */
        $creator = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $isPrivate = $data['isPrivate'] ?? false;

        try {
            $game = $this->gameService->createGame($isPrivate, $creator?->getId()?->toString());
            
            if ($creator) {
                $this->gameService->joinGame($game, $creator);
            }
            
            return $this->json([
                'success' => true,
                'game' => [
                    'id' => $game->getId()->toString(),
                    'code' => $game->getCode(),
                    'isPrivate' => $game->isPrivate(),
                    'status' => $game->getStatus()->value
                ]
            ], 201);
            
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    #[Route('/{code}/join', name: 'api_game_join', methods: ['POST'])]
    #[OA\Post(
        path: '/api/game/{code}/join',
        summary: 'Rejoindre une partie',
        tags: ['Game']
    )]
    #[OA\Parameter(
        name: 'code',
        in: 'path',
        required: true,
        description: 'Code de la partie',
        schema: new OA\Schema(type: 'string', example: 'ABC123')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Partie rejointe avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Partie rejointe avec succès')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'userId manquant')]
    #[OA\Response(response: 404, description: 'Partie ou utilisateur introuvable')]
    public function joinGame(string $code, Request $request): JsonResponse
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        try {
            $game = $this->gameRepository->findOneBy(['code' => strtoupper($code)]);
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }

            $this->gameService->joinGame($game, $user);
            return $this->json(['success' => true, 'message' => 'Partie rejointe avec succès'], 200);
        }
        catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    #[Route('/{code}/start', name: 'api_game_start', methods: ['POST'])]
    #[OA\Post(
        path: '/api/game/{code}/start',
        summary: 'Démarrer une partie',
        tags: ['Game']
    )]
    #[OA\Parameter(
        name: 'code',
        in: 'path',
        required: true,
        description: 'Code de la partie',
        schema: new OA\Schema(type: 'string', example: 'ABC123')
    )]
    #[OA\Response(
        response: 200,
        description: 'Partie démarrée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Partie démarrée')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Partie introuvable')]
    public function startGame(string $code, Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $proAiEnabled = $data['proAiEnabled'] ?? false;

        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }

            if (!$this->vertexAiClient->isConfigured()) {
                return $this->json(['success' => false, 'error' => 'IA_INDISPONIBLE'], 503);
            }

            if (!$this->budgetGuard->canMakeRequest()) {
                return $this->json(['success' => false, 'error' => 'IA_BUDGET_ATTEINT'], 429);
            }

            // Trouver ou créer le joueur IA
            $aiUser = $this->userRepository->findOneBy(['email' => 'ai@parry.game']);
            if (!$aiUser) {
                $aiUser = new User();
                $aiUser->setEmail('ai@parry.game');
                $aiUser->setPseudo('IA');
                $aiUser->setRoles(['ROLE_AI']);
                $aiUser->setPassword($this->passwordHasher->hashPassword($aiUser, bin2hex(random_bytes(16))));
                $this->entityManager->persist($aiUser);
                $this->entityManager->flush();
            }

            // Ajouter l'IA comme joueur
            $this->gameService->addAIPlayer($game, $aiUser);

            // Fait avant debutGame() pour que "proai" existe déjà quand l'event Mercure est publié
            if ($proAiEnabled) {
                $identifier = $game->getCode() ?? $game->getId()->toString();
                $redis = $this->gameRedisService->getRedis();
                $playersRaw = $redis->hgetall("game:{$identifier}:players") ?: [];

                $humanIds = [];
                foreach ($playersRaw as $playerId => $playerJson) {
                    $p = json_decode($playerJson, true);
                    if (!($p['isAI'] ?? false) && ($p['isAlive'] ?? true)) {
                        $humanIds[] = $playerId;
                    }
                }

                if (!empty($humanIds)) {
                    $proAiId = $humanIds[array_rand($humanIds)];
                    $redis->setex("game:{$identifier}:proai", 86400, $proAiId);
                }
            }

            $this->gameService->debutGame($game);

            return $this->json(['success' => true, 'message' => 'Partie démarrée'], 200);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
    }

    #[Route('/{code}/my-role', name: 'api_game_my_role', methods: ['GET'])]
    public function getMyRole(string $code): JsonResponse
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (!$game) {
            return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
        }

        $identifier = $game->getCode() ?? $game->getId()->toString();
        $proAiId = $this->gameRedisService->getRedis()->get("game:{$identifier}:proai");

        $role = ($proAiId !== null && $proAiId === $user->getId()->toString()) ? 'proai' : 'player';
        return $this->json(['success' => true, 'role' => $role]);
    }

    #[Route('/{code}/status', name: 'api_game_status', methods: ['GET'])]
    #[OA\Get(
        path: '/api/game/{code}/status',
        summary: 'Obtenir le statut d\'une partie',
        tags: ['Game']
    )]
    #[OA\Parameter(
        name: 'code',
        in: 'path',
        required: true,
        description: 'Code de la partie',
        schema: new OA\Schema(type: 'string', example: 'ABC123')
    )]
    #[OA\Response(
        response: 200,
        description: 'Statut de la partie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'game',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'code', type: 'string', example: 'ABC123'),
                        new OA\Property(property: 'status', type: 'string', example: 'in_progress'),
                        new OA\Property(property: 'playerCount', type: 'integer', example: 4),
                        new OA\Property(
                            property: 'players',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string'),
                                    new OA\Property(property: 'pseudo', type: 'string')
                                ]
                            )
                        ),
                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true)
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Partie introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function getGameStatus(string $code): JsonResponse
    {
        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }
            
            $players = [];
            foreach ($game->getPlayers() as $player) {
                $players[] = [
                    'id' => $player->getId()->toString(),
                    'pseudo' => $player->getPseudo(),
                ];
            }
            
            return $this->json([
                'success' => true,
                'game' => [
                    'id' => $game->getId()->toString(),
                    'code' => $game->getCode(),
                    'status' => $game->getStatus()->value,
                    'playerCount' => $game->getPlayers()->count(),
                    'players' => $players,
                    'startedAt' => $game->getStartedAt()?->format('c')
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'ERREUR_SERVEUR'], 500);
        }
    }

    #[Route('/{code}/state', name: 'api_game_state', methods: ['GET'])]
    public function getGameState(string $code): JsonResponse
    {
        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (!$game) {
            return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
        }

        $redis = $this->gameRedisService->getRedis();
        $identifier = $game->getCode() ?? $game->getId()->toString();

        // Infos utilisateur courant
        $isCreator = false;
        $myUserId = null;
        $currentUser = $this->getUser();
        if ($currentUser) {
            $myUserId = $currentUser->getId()->toString();

            if ($this->gameService->pruneDisconnectedPlayers($game)) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }
            $this->gameService->touchPlayerHeartbeat($game, $myUserId);

            $creatorId = $redis->get("game:{$identifier}:creator");
            $isCreator = $creatorId !== null && $creatorId === $myUserId;
        }

        // Players depuis Redis
        $playersRaw = $redis->hgetall("game:{$identifier}:players") ?: [];
        $players = [];

        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            $isAlive = $p['isAlive'] ?? true;
            $player = [
                'id'       => $playerId,
                'nickname' => $p['nickname'] ?? 'Joueur',
                'isAlive'  => $isAlive,
                'isAI'     => (bool)($p['isAI'] ?? false),
            ];

            if (!empty($p['alias'])) {
                $player['alias'] = $p['alias'];
            }

            if (!empty($p['sprites'])) {
                $player['sprites'] = $p['sprites'];
            }

            $players[] = $player;
        }

        // Spectateurs depuis Redis
        $spectatorsRaw = $redis->hgetall("game:{$identifier}:spectators") ?: [];
        $spectators = [];
        foreach ($spectatorsRaw as $spectatorId => $spectatorJson) {
            $s = json_decode($spectatorJson, true);
            $spectators[] = [
                'id'       => $spectatorId,
                'nickname' => $s['nickname'] ?? 'Spectateur',
            ];
        }
        $isSpectator = $myUserId !== null && isset($spectatorsRaw[$myUserId]);

        // Round depuis Redis
        $roundRaw = $redis->hgetall("game:{$identifier}:round") ?: [];
        $roundData = null;

        if (!empty($roundRaw) && !empty($roundRaw['roundId'])) {
            $status   = $roundRaw['status'] ?? null;
            $answers  = null;
            $revoteCandidates = null;
            $eliminatedPlayerId = null;

            if (in_array($status, ['en_attente_votes', 'termine'])) {
                $responsesRaw = $redis->hgetall("game:{$identifier}:round:reponses") ?: [];
                $answers = [];
                foreach ($responsesRaw as $pid => $rJson) {
                    $r = json_decode($rJson, true);
                    $answers[] = ['playerId' => $pid, 'text' => $r['reponse'] ?? ''];
                }
                usort($answers, fn($a, $b) => strcmp($a['playerId'], $b['playerId']));
            }

            $revoteRaw = $redis->get("game:{$identifier}:round:revote");
            if ($revoteRaw) {
                $revoteCandidates = json_decode($revoteRaw, true);
            }

            $roundEntity = $this->roundRepository->find($roundRaw['roundId']);
            if ($roundEntity && $roundEntity->getEliminatedPlayer()) {
                $eliminatedPlayerId = $roundEntity->getEliminatedPlayer()->getId()->toString();
            }

            $aliveCount = count(array_filter($players, fn($p) => $p['isAlive']));

            $roundData = [
                'id'                => $roundRaw['roundId'],
                'number'            => (int)($roundRaw['roundNumber'] ?? 1),
                'status'            => $status,
                'question'          => $roundRaw['question'] ?? null,
                'questionMasterId'  => $roundRaw['questionAskedBy'] ?? null,
                'answeredCount'     => (int)$redis->hlen("game:{$identifier}:round:reponses"),
                'votedCount'        => (int)$redis->hlen("game:{$identifier}:round:votes"),
                'totalAlive'        => $aliveCount,
                'answers'           => $answers,
                'revoteCandidates'  => $revoteCandidates,
                'eliminatedPlayerId'=> $eliminatedPlayerId,
            ];
        }

        // Vérification abandon (joueur parti en cours de partie ou crash IA)
        $abandonedBy = $redis->get("game:{$identifier}:abandoned");
        $abandoned = $abandonedBy !== null
            && $game->getStatus()->value === 'in_progress';

        return $this->json([
            'success' => true,
            'game' => [
                'id'        => $game->getId()->toString(),
                'code'      => $game->getCode(),
                'status'    => $game->getStatus()->value,
                'winner'    => $game->getWinnerType()?->value,
                'isCreator' => $isCreator,
                'myUserId'  => $myUserId,
                'abandoned' => $abandoned,
                'abandonedReason' => $abandoned ? $abandonedBy : null,
                'proAiEnabled' => $redis->exists("game:{$identifier}:proai") > 0,
                'players'   => $players,
                'isSpectator' => $isSpectator,
                'spectators' => $spectators,
                'round'     => $roundData,
            ]
        ]);
    }
    #[Route('/active', name: 'api_game_active', methods: ['GET'])]
    #[OA\Get(path: '/api/game/active', summary: 'Retourne la partie active de l\'utilisateur connecté', tags: ['Game'])]
    #[OA\Response(response: 200, description: 'Code de partie active ou null')]
    public function getActiveGame(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $redis = $this->gameRedisService->getRedis();
        $code = $redis->get('user:' . $user->getId()->toString() . ':activeGame');

        if (!$code) {
            return $this->json(['success' => true, 'code' => null]);
        }

        // Vérifie que la partie existe encore et est active
        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (
            !$game ||
            $game->getStatus()->value === 'finished' ||
            $redis->get("game:{$code}:abandoned") !== null
        ) {
            $redis->del(['user:' . $user->getId()->toString() . ':activeGame']);
            return $this->json(['success' => true, 'code' => null]);
        }

        return $this->json(['success' => true, 'code' => $code, 'isCreator' => $redis->get("game:{$code}:creator") === $user->getId()->toString()]);
    }

    #[Route('/{code}/leave', name: 'api_game_leave', methods: ['POST'])]
    #[OA\Post(path: '/api/game/{code}/leave', summary: 'Quitter la partie (créateur: arrêt total, joueur: retrait)', tags: ['Game'])]
    #[OA\Response(response: 200, description: 'Sortie traitée')]
    public function leaveGame(string $code): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (!$game) {
            $this->gameRedisService->getRedis()->del(['user:' . $user->getId()->toString() . ':activeGame']);
            return $this->json(['success' => true], 200);
        }

        $redis = $this->gameRedisService->getRedis();
        $userId = $user->getId()->toString();
        $creatorId = $redis->get("game:{$code}:creator");

        // Si le créateur se déconnecte, la partie est arrêtée pour tout le monde
        if ($creatorId !== null && $creatorId === $userId) {
            $this->gameService->deleteGame($game);
            return $this->json(['success' => true, 'stopped' => true], 200);
        }

        // Joueur standard : on le retire simplement de la partie
        if ($game->getPlayers()->contains($user)) {
            $game->removePlayer($user);
            $this->entityManager->flush();
            $redis->hdel("game:{$code}:players", [$userId]);
        } else {
            // Spectateur : on le retire de la liste des spectateurs
            $redis->hdel("game:{$code}:spectators", [$userId]);
        }

        $redis->del(['user:' . $userId . ':activeGame']);

        return $this->json(['success' => true, 'removed' => true], 200);
    }

    #[Route('/{code}/restart', name: 'api_game_restart', methods: ['POST'])]
    #[OA\Post(path: '/api/game/{code}/restart', summary: 'Relancer la partie (même code)', tags: ['Game'])]
    #[OA\Response(response: 200, description: 'Partie réinitialisée')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Partie introuvable')]
    public function restartGame(string $code): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (!$game) {
            return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
        }

        try {
            $this->gameService->restartGame($game);
            return $this->json(['success' => true], 200);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'ERREUR_SERVEUR'], 500);
        }
    }

    #[Route('/{code}/delete', name: 'api_game_delete', methods: ['POST'])]
    #[OA\Post(path: '/api/game/{code}/delete', summary: 'Supprimer la partie et son code', tags: ['Game'])]
    #[OA\Response(response: 200, description: 'Partie supprimée')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Partie introuvable')]
    public function deleteGame(string $code): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $game = $this->gameRepository->findOneBy(['code' => $code]);
        if (!$game) {
            return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
        }

        // Seul le créateur peut supprimer la partie
        $creatorId = $this->gameRedisService->getRedis()->get("game:{$code}:creator");
        if ($creatorId !== null && $creatorId !== $user->getId()->toString()) {
            return $this->json(['success' => false, 'error' => 'NON_AUTORISE'], 403);
        }

        try {
            $this->gameService->deleteGame($game);
            return $this->json(['success' => true], 200);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'ERREUR_SERVEUR'], 500);
        }
    }

    #[Route('/{code}/check-victory', name: 'api_game_check_victory', methods: ['POST'])]
    public function checkVictory(string $code): JsonResponse {
        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);

            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }

            $winner = $this->gameService->victorireCondition($game);

            if ($winner) {
                $this->gameService->finGame($game, $winner);

                return $this->json(['success' => true, 'gameOver' => true, 'winner' => $winner], 200);
            }

            return $this->json(['success' => true, 'gameOver' => false], 200);
        }
        catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'ERREUR_SERVEUR'], 500);
        }
    }

    #[Route('/animals', name: 'api_animals_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/game/animals',
        summary: 'Get all animal configurations with sprite paths',
        tags: ['Game']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of all animals',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'animals',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'alias', type: 'string'),
                            new OA\Property(property: 'animalName', type: 'string'),
                            new OA\Property(
                                property: 'sprites',
                                properties: [
                                    new OA\Property(property: 'response', type: 'string'),
                                    new OA\Property(property: 'question', type: 'string'),
                                    new OA\Property(property: 'elimination', type: 'string'),
                                ]
                            ),
                        ]
                    )
                )
            ]
        )
    )]
    public function getAnimals(): JsonResponse
    {
        try {
            $animals = $this->animalConfigRepository->findAll();
            $result = [];

            foreach ($animals as $animal) {
                $result[] = [
                    'alias' => $animal->getAlias(),
                    'animalName' => $animal->getAnimalName(),
                    'sprites' => [
                        'response' => $animal->getResponseSprite(),
                        'question' => $animal->getQuestionSprite(),
                        'elimination' => $animal->getEliminationSprite(),
                    ],
                    'color' => $animal->getColor(),
                    'description' => $animal->getDescription(),
                ];
            }

            return $this->json(['success' => true, 'animals' => $result], 200);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'ERREUR_SERVEUR'], 500);
        }
    }

    #[Route('/test-setup', name: 'api_game_test_setup', methods: ['POST'])]
    #[OA\Post(path: '/api/game/test-setup', summary: '[DEV] Setup une partie de test avec joueurs et sprites', tags: ['Game'])]
    public function testSetup(): JsonResponse
    {
        if ('dev' !== $_ENV['APP_ENV'] ?? 'prod') {
            return $this->json(['success' => false, 'error' => 'ENDPOINT_TEST_DESACTIVE'], 403);
        }

        try {
            // 1. Créer une partie test
            $game = new Game();
            $testCode = substr(strtoupper(uniqid()), 0, 6);
            $game->setCode($testCode);
            $game->setStatus(GameStatus::IN_PROGRESS);
            $game->setIsPrivate(true);
            $this->entityManager->persist($game);
            $this->entityManager->flush();

            $gameCode = $game->getCode();

            // 2. Créer 3 joueurs test
            $testUsers = [];
            $animals = $this->animalConfigRepository->findAll();

            for ($i = 0; $i < 3; $i++) {
                $user = new User();
                $user->setPseudo("TestPlayer$i");
                $user->setEmail("test$i@test.local");
                $user->setPassword($this->passwordHasher->hashPassword($user, 'test'));
                $this->entityManager->persist($user);
                $testUsers[] = $user;
            }
            $this->entityManager->flush();

            // 3. Ajouter les joueurs à la partie avec sprites
            foreach ($testUsers as $i => $user) {
                $game->addPlayer($user);

                // Assigner alias aléatoire
                $animal = $animals[$i % count($animals)];
                $alias = new GamePlayerAlias();
                $alias->setGame($game);
                $alias->setPlayer($user);
                $alias->setAnimalConfig($animal);
                $this->entityManager->persist($alias);

                // Ajouter en Redis avec sprites
                $this->gameRedisService->addPlayer(
                    $gameCode,
                    $user->getId()->toString(),
                    $user->getPseudo(),
                    false,
                    $animal->getAlias(),
                    $animal->getResponseSprite(),
                    $animal->getQuestionSprite(),
                    $animal->getEliminationSprite()
                );
            }
            $this->entityManager->flush();

            // 4. Setup une question et mettre en phase réponses
            $question = "Quelle est ta couleur préférée?";
            $redis = $this->gameRedisService->getRedis();
            $redis->hset("game:$gameCode:round", 'status', 'en_attente_reponses');
            $redis->hset("game:$gameCode:round", 'question', $question);
            $redis->hset("game:$gameCode:round", 'questionAskedBy', $testUsers[0]->getId()->toString());
            $redis->hset("game:$gameCode:round", 'roundNumber', 1);

            return $this->json([
                'success' => true,
                'gameCode' => $gameCode,
                'message' => 'Partie de test créée en phase réponses',
                'players' => array_map(fn($u) => [
                    'id' => $u->getId()->toString(),
                    'pseudo' => $u->getPseudo(),
                    'email' => $u->getEmail(),
                ], $testUsers),
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'ERREUR_SETUP_TEST',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}