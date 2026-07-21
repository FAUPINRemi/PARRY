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

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        // Récupérer tous les joueurs vivants depuis Redis
        $playersRaw = $this->gameRedisService->getRedis()->hgetall("game:{$gameIdentifier}:players") ?: [];

        $humanAliveIds = [];
        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            if (($p['isAlive'] ?? true) && !($p['isAI'] ?? false)) {
                $humanAliveIds[] = $playerId;
            }
        }

        // Récupérer les réponses déjà soumises
        $reponsesRaw = $this->gameRedisService->getRedis()->hgetall("game:{$gameIdentifier}:round:reponses") ?: [];

        // Vérifier si tous les humains ont répondu
        $humanResponded = 0;
        $humanResponseTexts = [];
        foreach ($humanAliveIds as $humanId) {
            if (isset($reponsesRaw[$humanId])) {
                $humanResponded++;
                $decoded = json_decode($reponsesRaw[$humanId], true);
                if (isset($decoded['reponse'])) {
                    $humanResponseTexts[] = $decoded['reponse'];
                }
            }
        }

        // Si tous les humains n'ont pas encore répondu, on attend
        if ($humanResponded < count($humanAliveIds)) {
            return;
        }

        $response = $this->aiOrchestrator->generateResponse($question, [
            'human_responses' => $humanResponseTexts
        ]);

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

        $revoteData = $this->gameRedisService->getRedis()->get("game:{$gameIdentifier}:round:revote");
        $revoteCandidats = $revoteData ? json_decode($revoteData, true) : null;

        if ($revoteCandidats !== null) {
            // Ne montrer à l'IA que les joueurs éligibles au revote (+ sa propre réponse pour le contexte du prompt)
            $playersWithResponses = array_values(array_filter(
                $playersWithResponses,
                fn($p) => $p['is_ai'] || in_array($p['player_id'], $revoteCandidats, true)
            ));
        }

        $question = $round->getQuestion();
        $votedFor = $this->aiOrchestrator->voteAsHuman($playersWithResponses, $question);

        if ($revoteCandidats !== null && !in_array($votedFor, $revoteCandidats, true)) {
            // Filet de sécurité si le LLM ignore la consigne : forcer un choix valide parmi les candidats du revote
            $choixValides = array_values(array_filter(
                $revoteCandidats,
                fn($id) => $id !== $aiUser->getId()->toString()
            ));
            $votedFor = $choixValides[array_rand($choixValides)] ?? $revoteCandidats[array_rand($revoteCandidats)];
        }

        $this->roundService->voteRound($round, $aiUser, $votedFor);
    }
}