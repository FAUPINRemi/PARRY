<?php
namespace App\Controller\Api;

use App\Entity\Round;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use App\Service\Game\RoundService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/round')]
class RoundController extends AbstractController
{
    public function __construct(
        private readonly RoundService $roundService,
        private readonly GameRepository $gameRepository,
        private readonly RoundRepository $roundRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/create', name: 'api_round_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/create',
        summary: 'Créer un nouveau round',
        tags: ['Round']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['gameCode', 'questionMasterId'],
            properties: [
                new OA\Property(property: 'gameCode', type: 'string', example: 'ABC123'),
                new OA\Property(property: 'questionMasterId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Round créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'round',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'roundNumber', type: 'integer')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    #[OA\Response(response: 404, description: 'Partie ou Question Master introuvable')]
    public function createRound(Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $gameCode = $data['gameCode'] ?? null;

        if (!$gameCode) {
            return $this->json(['success' => false, 'error' => 'GAME_CODE_REQUIS'], 400);
        }

        try {
            $game = $this->gameRepository->findOneBy(['code' => $gameCode]);
            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }

            $round = $this->roundService->createRound($game);

            return $this->json([
                'success' => true,
                'round' => [
                    'id' => $round->getId()->toString(),
                    'roundNumber' => $round->getRoundNumber()
                ]
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    #[Route('/{roundId}/question', name: 'api_round_question', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/{roundId}/question',
        summary: 'Soumettre une question',
        tags: ['Round']
    )]
    #[OA\Parameter(
        name: 'roundId',
        in: 'path',
        required: true,
        description: 'ID du round',
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId', 'question'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'question', type: 'string', example: 'Es-tu le traître ?')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Question envoyée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Question envoyée')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    #[OA\Response(response: 404, description: 'Round ou utilisateur introuvable')]
    public function submitQuestion(string $roundId, Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;
        $questionTexte = $data['question'] ?? null;

        if (!$userId || !$questionTexte) {
            return $this->json(['success' => false, 'error' => 'DONNEES_MANQUANTES'], 400);
        }

        try {
            $round = $this->roundRepository->find($roundId);
            if (!$round) {
                return $this->json(['success' => false, 'error' => 'ROUND_INTROUVABLE'], 404);
            }

            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json(['success' => false, 'error' => 'UTILISATEUR_INTROUVABLE'], 404);
            }

            $this->roundService->questionRound($round, $user, $questionTexte);

            return $this->json(['success' => true, 'message' => 'Question envoyée'], 200);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
    }

    #[Route('/{roundId}/response', name: 'api_round_response', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/{roundId}/response',
        summary: 'Soumettre une réponse',
        tags: ['Round']
    )]
    #[OA\Parameter(
        name: 'roundId',
        in: 'path',
        required: true,
        description: 'ID du round',
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId', 'response'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'response', type: 'string', example: 'Non, je suis innocent !')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Réponse soumise',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Réponse soumise')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    #[OA\Response(response: 404, description: 'Round ou utilisateur introuvable')]
    public function submitResponse(string $roundId, Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;
        $responseTexte = $data['response'] ?? null;

        if (!$userId || !$responseTexte) {
            return $this->json(['success' => false, 'error' => 'DONNEES_MANQUANTES'], 400);
        }

        try {
            $round = $this->roundRepository->find($roundId);
            if (!$round) {
                return $this->json(['success' => false, 'error' => 'ROUND_INTROUVABLE'], 404);
            }

            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json(['success' => false, 'error' => 'UTILISATEUR_INTROUVABLE'], 404);
            }

            $this->roundService->reponseRound($round, $user, $responseTexte);

            return $this->json(['success' => true, 'message' => 'Réponse soumise'], 200);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    #[Route('/{roundId}/vote', name: 'api_round_vote', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/{roundId}/vote',
        summary: 'Voter pour éliminer un joueur',
        tags: ['Round']
    )]
    #[OA\Parameter(
        name: 'roundId',
        in: 'path',
        required: true,
        description: 'ID du round',
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['voterId', 'targetPlayerId'],
            properties: [
                new OA\Property(property: 'voterId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'targetPlayerId', type: 'string', example: '660e8400-e29b-41d4-a716-446655440000')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Vote enregistré',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Vote enregistré')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    #[OA\Response(response: 404, description: 'Round ou votant introuvable')]
    public function submitVote(string $roundId, Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $voterId = $data['voterId'] ?? null;
        $targetPlayerId = $data['targetPlayerId'] ?? null;

        if (!$voterId || !$targetPlayerId) {
            return $this->json(['success' => false, 'error' => 'DONNEES_MANQUANTES'], 400);
        }

        try {
            $round = $this->roundRepository->find($roundId);
            if (!$round) {
                return $this->json(['success' => false, 'error' => 'ROUND_INTROUVABLE'], 404);
            }

            $voter = $this->userRepository->find($voterId);
            if (!$voter) {
                return $this->json(['success' => false, 'error' => 'VOTANT_INTROUVABLE'], 404);
            }

            $this->roundService->voteRound($round, $voter, $targetPlayerId);

            return $this->json(['success' => true, 'message' => 'Vote enregistré'], 200);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
    }

    #[Route('/{roundId}/eliminate', name: 'api_round_eliminate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/{roundId}/eliminate',
        summary: 'Éliminer le joueur avec le plus de votes',
        tags: ['Round']
    )]
    #[OA\Parameter(
        name: 'roundId',
        in: 'path',
        required: true,
        description: 'ID du round',
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\Response(
        response: 200,
        description: 'Élimination effectuée ou revote nécessaire',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'revote', type: 'boolean', example: false),
                new OA\Property(property: 'eliminatedPlayerId', type: 'string', nullable: true),
                new OA\Property(property: 'message', type: 'string', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Round introuvable')]
    public function eliminatePlayer(string $roundId): JsonResponse {
        try {
            $round = $this->roundRepository->find($roundId);
            if (!$round) {
                return $this->json(['success' => false, 'error' => 'ROUND_INTROUVABLE'], 404);
            }

            $eliminatedPlayerId = $this->roundService->eliminerJoueur($round);

            if ($eliminatedPlayerId === null) {
                return $this->json(['success' => true, 'revote' => true, 'message' => 'Égalité détectée, revote nécessaire'], 200);
            }

            return $this->json(['success' => true, 'revote' => false, 'eliminatedPlayerId' => $eliminatedPlayerId], 200);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
    }

    #[Route('/{roundId}/finish', name: 'api_round_finish', methods: ['POST'])]
    #[OA\Post(
        path: '/api/round/{roundId}/finish',
        summary: 'Terminer le round',
        tags: ['Round']
    )]
    #[OA\Parameter(
        name: 'roundId',
        in: 'path',
        required: true,
        description: 'ID du round',
        schema: new OA\Schema(type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
    )]
    #[OA\Response(
        response: 200,
        description: 'Round terminé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Round terminé')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Round introuvable')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
    public function finishRound(string $roundId): JsonResponse
    {
        try {
            $round = $this->roundRepository->find($roundId);
            if (!$round) {
                return $this->json(['success' => false, 'error' => 'ROUND_INTROUVABLE'], 404);
            }

            $this->roundService->finRound($round);

            return $this->json(['success' => true, 'message' => 'Round terminé'], 200);
        } catch (\Exception $e) {
            return $this->json(['success' => false,'error' => 'ERREUR_SERVEUR'], 500);
        }
    }
}