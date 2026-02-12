<?php

namespace App\Controller\Api;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use App\Service\AI\AIJoueurService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ai')]
class AIController extends AbstractController
{
    public function __construct(
        private GameRepository $gameRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/play/{gameCode}', name: 'ai_play', methods: ['POST'])]
    #[OA\Post(
        path: '/api/ai/play/{gameCode}',
        summary: 'Faire jouer l\'IA',
        tags: ['AI']
    )]
    #[OA\Parameter(
        name: 'gameCode',
        in: 'path',
        required: true,
        description: 'Code de la partie',
        schema: new OA\Schema(type: 'string', example: 'ABC123')
    )]
    #[OA\Response(
        response: 200,
        description: 'L\'IA a joué son action',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'L\'IA a joué son action')
            ]
        )
    )]
    public function playAI(string $gameCode, AIJoueurService $aiJoueurService): JsonResponse
    {
        $game = $this->gameRepository->findOneBy(['code' => $gameCode]);
        
        if (!$game) {
            return $this->json(['error' => 'Partie non trouvée'], Response::HTTP_NOT_FOUND);
        }

        if ($game->getStatus() !== GameStatus::IN_PROGRESS) {
            return $this->json([
                'error' => 'La partie n\'est pas en cours',
                'status' => $game->getStatus()->value
            ], Response::HTTP_BAD_REQUEST);
        }

        $rounds = $game->getRounds();
        if ($rounds->isEmpty()) {
            return $this->json(['error' => 'Aucun round actif'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // L'IA ne s'instancie réellement qu'à cette ligne précise
            $aiJoueurService->playRound($rounds->last());
            
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