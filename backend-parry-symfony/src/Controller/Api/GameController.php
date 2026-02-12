<?php
namespace App\Controller\Api;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\Game\GameService;
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
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
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
        $data = json_decode($request->getContent(), true);
        $isPrivate = $data['isPrivate'] ?? false;
        
        try {
            $game = $this->gameService->createGame($isPrivate);
            
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
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'USER_ID_REQUIS'], 400);
        }
        
        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }
            
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                return $this->json(['success' => false, 'error' => 'UTILISATEUR_INTROUVABLE'], 404);
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
    public function startGame(string $code): JsonResponse {
        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);
            
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }
            $this->gameService->debutGame($game);
            
            return $this->json(['success' => true, 'message' => 'Partie démarrée'], 200);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
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
                    'pseudo' => $player->getPseudo()
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

    #[Route('/{code}/check-victory', name: 'api_game_check_victory', methods: ['POST'])]
    #[OA\Post(
        path: '/api/game/{code}/check-victory',
        summary: 'Vérifier les conditions de victoire',
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
        description: 'Vérification effectuée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'gameOver', type: 'boolean', example: false),
                new OA\Property(property: 'winner', type: 'string', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Partie introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
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
}