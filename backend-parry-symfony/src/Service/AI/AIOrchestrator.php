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
    
    
    //Générer une question pour un round
    public function generateQuestion(array $context = []): string
    {
        $question = $this->questionGenerator->generateQuestion($context);
        
        // MODÉRATION DÉSACTIVÉE pour réduire les appels Gemini
       
        // À réactiver manuellement si besoin via moderateUserContent()
        /*
        $modResult = $this->moderation->moderateContent($question, 'question');
        
        if (!$modResult['is_safe']) {
            throw new \RuntimeException('Question générée inappropriée : ' . implode(', ', $modResult['flagged_reasons']));
        }
        */
        
        return $question;
    }
    
    //Généré une réponse 
    public function generateResponse(string $question, array $context = []): string
    {
        $response = $this->responseGenerator->generateResponse($question, $context);
        
        // MODÉRATION DÉSACTIVÉE pour réduire les appels Gemini
        // Économise ~1 requête par réponse
        /*
        $modResult = $this->moderation->moderateContent($response, 'response');
        
        if (!$modResult['is_safe']) {
            throw new \RuntimeException('Réponse générée inappropriée : ' . implode(', ', $modResult['flagged_reasons']));
        }
        */
        
        return $response;
    }
    
 
    public function voteAsHuman(
        array $playersWithResponses,
        string $question,
        array $otherPlayersVotes = []
    ): string {
        return $this->voteAnalyzer->voteAsHuman($playersWithResponses, $question, $otherPlayersVotes);
    }
    
  
    public function analyzeRound(array $roundData): array
    {
        $analysis = $this->monitoring->analyzeRound($roundData, $this->gameHistory);
        
        // Sauvegarder dans l'historique
        $this->gameHistory[] = array_merge($roundData, [
            'human_likeness_score' => $analysis['human_likeness_score'] ?? 0
        ]);
        
        return $analysis;
    }
    

    public function getGameStats(): array
    {
        return $this->monitoring->computeStats($this->gameHistory);
    }
    
    
    public function moderateUserContent(string $content, string $type = 'response'): array
    {
        return $this->moderation->moderateContent($content, $type);
    }
    

    public function moderateBatch(array $contents): array
    {
        $allContent = implode("\n---\n", $contents);
        return $this->moderation->moderateContent($allContent, 'batch');
    }
}