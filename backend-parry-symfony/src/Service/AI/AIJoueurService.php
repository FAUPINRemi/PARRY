<?php

namespace App\Service\AI;

use App\Entity\Round;
use App\Repository\UserRepository;
use App\Service\Game\RoundService;
use App\Service\GameRedisService;
use Doctrine\ORM\EntityManagerInterface;

class AIJoueurService
{
    public function __construct(
        private readonly AIOrchestrator $aiOrchestrator,
        private readonly RoundService $roundService,
        private readonly UserRepository $userRepository,
        private readonly GameRedisService $gameRedisService,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function playRound(Round $round): void {

        $aiUser = $this->userRepository->findOneBy(['email' => 'ai@parry.game']);

        if (!$aiUser) {
            throw new \RuntimeException('UTILISATEUR_IA_INTROUVABLE', 500);
        }

        // Récupérer le statut du round depuis Redis
        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $statusRound = $this->gameRedisService->getRedis()->hget(
            "game:{$gameIdentifier}:round",
            'status'
        );

        // Agir selon le statut
        switch ($statusRound) {
            case 'en_attente_question':
                $this->aiSendQuestion($round, $aiUser);
                break;

            case 'en_attente_reponses':
                $this->aiResponse($round, $aiUser);
                break;

            case 'en_attente_votes':
                $this->aiVote($round, $aiUser, $gameIdentifier);
                break;

            default:
                throw new \RuntimeException('STATUT_ROUND_INVALIDE', 400);
        }
    }

    private function aiSendQuestion(Round $round, $aiUser): void {

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        // N'agir que si l'IA est le question master désigné
        $qmId = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:round", 'questionAskedBy');
        if ($qmId !== $aiUser->getId()->toString()) {
            return;
        }

        $previousQuestions = [];

        foreach ($game->getRounds() as $previousRound) {
            if ($previousRound->getQuestion()) {
                $previousQuestions[] = $previousRound->getQuestion();
            }
        }

        $question = $this->aiOrchestrator->generateQuestion([
            'round_number' => $round->getRoundNumber(),
            'previous_questions' => $previousQuestions
        ]);

        $this->roundService->questionRound($round, $aiUser, $question);
    }

    private function aiResponse(Round $round, $aiUser): void {

        $question = $round->getQuestion();

        if (!$question) {
            throw new \RuntimeException('AUCUNE_QUESTION_DANS_LE_ROUND', 400);
        }

        $response = $this->aiOrchestrator->generateResponse($question);

        $this->roundService->reponseRound($round, $aiUser, $response);
    }

    private function aiVote(Round $round, $aiUser, string $gameIdentifier): void {

        $reponses = $this->gameRedisService->getRedis()->hgetall(
            "game:{$gameIdentifier}:round:reponses"
        );

        if (empty($reponses)) {
            throw new \RuntimeException('AUCUNE_REPONSE_DISPONIBLE', 400);
        }

        $playersWithResponses = [];

        foreach ($reponses as $playerId => $reponseData) {
            $reponseArray = json_decode($reponseData, true);

            $playerData = $this->gameRedisService->getRedis()->hget(
                "game:{$gameIdentifier}:players",
                $playerId
            );

            $isAI = false;

            if ($playerData) {
                $pData = json_decode($playerData, true);
                $isAI = $pData['isAI'] ?? false;
            }

            $playersWithResponses[] = [
                'player_id' => $playerId,
                'response' => $reponseArray['reponse'],
                'is_ai' => $isAI
            ];
        }

        $question = $round->getQuestion();
        $votedFor = $this->aiOrchestrator->voteAsHuman($playersWithResponses, $question);

        $this->roundService->voteRound($round, $aiUser, $votedFor);
    }
}