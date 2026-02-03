<?php

namespace App\Service\AI;

class AIOrchestrator
{
    private array $gameHistory = [];
    public function __construct(
        private readonly QuestionGeneratorAI $questionGenerator,
        private readonly ResponseGeneratorAI $responseGenerator,
        private readonly VoteAnalyzerAI $voteAnalyzer,
        private readonly MonitoringAI $monitoring,
        private readonly ModerationAI $moderation
    ) {}
    
    /**
     * Génère une question pour une nouvelle manche
     */
    public function generateQuestion(array $context = []): string
    {
        $question = $this->questionGenerator->generateQuestion($context);
        
        // Modération de la question
        $modResult = $this->moderation->moderateContent($question, 'question');
        
        if (!$modResult['is_safe']) {
            // Regénérer si problème (ou lever une exception)
            throw new \RuntimeException('Question générée inappropriée : ' . implode(', ', $modResult['flagged_reasons']));
        }
        
        return $question;
    }
    
    /**
     * Génère une réponse de l'IA à une question
     */
    public function generateResponse(string $question, array $context = []): string
    {
        $response = $this->responseGenerator->generateResponse($question, $context);
        
        // Modération de la réponse
        $modResult = $this->moderation->moderateContent($response, 'response');
        
        if (!$modResult['is_safe']) {
            throw new \RuntimeException('Réponse générée inappropriée : ' . implode(', ', $modResult['flagged_reasons']));
        }
        
        return $response;
    }
    
    /**
     * L'IA vote comme un humain
     * @param array $playersWithResponses [['player_id' => 'uuid', 'response' => 'text', 'is_ai' => bool], ...]
     * @param array $otherPlayersVotes [['player_id' => 'uuid', 'voted_for' => 'player_id'], ...]
     */
    public function voteAsHuman(
        array $playersWithResponses,
        string $question,
        array $otherPlayersVotes = []
    ): string {
        return $this->voteAnalyzer->voteAsHuman($playersWithResponses, $question, $otherPlayersVotes);
    }
    /**
     * Analyse une manche et propose des améliorations
     */
    public function analyzeRound(array $roundData): array
    {
        $analysis = $this->monitoring->analyzeRound($roundData, $this->gameHistory);
        // Sauvegarder dans l'historique
        $this->gameHistory[] = array_merge($roundData, [
            'human_likeness_score' => $analysis['human_likeness_score'] ?? 0
        ]);
        return $analysis;
    }
    /**
     * Obtenir les statistiques de performance
     */
    public function getGameStats(): array
    {
        return $this->monitoring->computeStats($this->gameHistory);
    }
    
    /**
     * Modère un contenu utilisateur
     */
    public function moderateUserContent(string $content, string $type = 'response'): array
    {
        return $this->moderation->moderateContent($content, $type);
    }
}