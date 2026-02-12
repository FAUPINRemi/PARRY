<?php

namespace App\Controller\Api;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use App\Service\AI\AIJoueurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ai')]
class AIController extends AbstractController
{
    public function __construct(
        private AIJoueurService $aiJoueurService,
        private GameRepository $gameRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/play/{gameCode}', name: 'ai_play', methods: ['POST'])]
    public function playAI(string $gameCode): JsonResponse
    {
        // 1. Récupérer la partie
        $game = $this->gameRepository->findOneBy(['code' => $gameCode]);
        
        if (!$game) {
            return $this->json([
                'error' => 'Partie non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        // 2. Vérifier que la partie est démarrée
        if ($game->getStatus() !== GameStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'La partie n\'est pas en cours',
                'status' => $game->getStatus()->value
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. Récupérer le round actif
        $rounds = $game->getRounds();
        if ($rounds->isEmpty()) {
            return $this->json([
                'error' => 'Aucun round actif dans cette partie'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Prendre le dernier round (le round actif)
        $currentRound = $rounds->last();

        // 4. Laisser l'IA jouer son tour
        try {
            $this->aiJoueurService->playRound($currentRound);
            
            return $this->json([
                'success' => true,
                'message' => 'L\'IA a joué son action'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'action de l\'IA',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}