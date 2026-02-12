<?php

namespace App\Controller\Api;

use App\Entity\Round;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\RoundRepository;
use App\Repository\UserRepository;
use App\Service\Game\RoundService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function createRound(Request $request): JsonResponse {

        $data = json_decode($request->getContent(), true);
        $gameCode = $data['gameCode'] ?? null;
        $questionMasterId = $data['questionMasterId'] ?? null;

        if (!$gameCode || !$questionMasterId) {
            return $this->json(['success' => false, 'error' => 'GAME_CODE_ET_QUESTION_MASTER_REQUIS'], 400);
        }

        try {
            $game = $this->gameRepository->findOneBy(['code' => $gameCode]);

            if (!$game) {
                return $this->json(['success' => false, 'error' => 'PARTIE_INTROUVABLE'], 404);
            }

            $questionMaster = $this->userRepository->find($questionMasterId);

            if (!$questionMaster) {
                return $this->json(['success' => false, 'error' => 'QUESTION_MASTER_INTROUVABLE'], 404);
            }

            $round = $this->roundService->createRound($game, $questionMaster);

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

            return $this->json([ 'success' => true, 'revote' => false, 'eliminatedPlayerId' => $eliminatedPlayerId], 200);
        } catch (\RuntimeException $e) {

            return $this->json(['success' => false, 'error' => $e->getMessage()], $e->getCode());
        }
    }

    #[Route('/{roundId}/finish', name: 'api_round_finish', methods: ['POST'])]
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