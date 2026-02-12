<?php

namespace App\Controller\Api;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\Game\GameService;
use Doctrine\ORM\EntityManagerInterface;
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
    
    // Création d'une partie
    #[Route('/create', name: 'api_game_create', methods: ['POST'])]
    
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
                ]], 201);
                
                } catch (\RuntimeException $e) {
                    
                    return $this->json([
                        'success' => false,
                        'error' => $e->getMessage()
                    ], $e->getCode());
                }
    }

    // Rejoindre une partie
    #[Route('/{code}/join', name: 'api_game_join', methods: ['POST'])]
    public function joinGame(string $code, Request $request): JsonResponse{

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
            return $this->json([ 'success' => true, 'message' => 'Partie rejointe avec succès'], 200);
        } 
        
        catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    // Start partie
    #[Route('/{code}/start', name: 'api_game_start', methods: ['POST'])]
    
    public function startGame(string $code): JsonResponse {
        
        try {
            $game = $this->gameRepository->findOneBy(['code' => $code]);
            
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }
            $this->gameService->debutGame($game);
            
            return $this->json([ 'success' => true, 'message' => 'Partie démarrée' ], 200);
        } catch (\RuntimeException $e) {
            
            return $this->json([ 'success' => false, 'error' => $e->getMessage()
        ], $e->getCode());
    }
    }


    // Statut de la partie
    #[Route('/{code}/status', name: 'api_game_status', methods: ['GET'])]
    
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

    // Verif condition de victoire
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
}

