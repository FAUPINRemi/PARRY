<?php

namespace App\Service\AI;

class MonitoringAI
{
    private const TEMPERATURE = 0.4;
    private const MAX_TOKENS = 800;
    
    public function __construct(
        private readonly GeminiClient $geminiClient
    ) {}
    
    /**
     * Analyse les réponses de l'IA vs les humains et propose des améliorations
     * 
     * @param array $roundData Format: ['question' => string, 'ai_response' => string, 'human_responses' => [...], 'ai_detected' => bool]
     * @return array ['analysis' => string, 'prompt_suggestions' => string, 'stats' => [...]]
     */
    public function analyzeRound(array $roundData, array $gameHistory = []): array
    {
        $prompt = $this->buildAnalysisPrompt($roundData, $gameHistory);
        
        $responseText = $this->geminiClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
        
        return $this->parseAnalysisResponse($responseText);
    }
    
  
    public function computeStats(array $gameHistory): array
    {
        $totalRounds = count($gameHistory);
        if ($totalRounds === 0) {
            return [
                'success_rate' => 0,
                'detection_rate' => 0,
                'avg_human_likeness' => 0,
                'total_rounds' => 0
            ];
        }
        
        $detections = 0;
        $humanLikenessScores = [];
        
        foreach ($gameHistory as $round) {
            if ($round['ai_detected'] ?? false) {
                $detections++;
            }
            if (isset($round['human_likeness_score'])) {
                $humanLikenessScores[] = $round['human_likeness_score'];
            }
        }
        
        $successRate = (($totalRounds - $detections) / $totalRounds) * 100;
        $detectionRate = ($detections / $totalRounds) * 100;
        $avgHumanLikeness = !empty($humanLikenessScores) 
            ? array_sum($humanLikenessScores) / count($humanLikenessScores)
            : 0;
        
        return [
            'success_rate' => round($successRate, 2),
            'detection_rate' => round($detectionRate, 2),
            'avg_human_likeness' => round($avgHumanLikeness, 2),
            'total_rounds' => $totalRounds,
            'successful_rounds' => $totalRounds - $detections,
            'detected_rounds' => $detections
        ];
    }
    
    private function buildAnalysisPrompt(array $roundData, array $gameHistory): string
    {
        $question = $roundData['question'] ?? '';
        $aiResponse = $roundData['ai_response'] ?? '';
        $humanResponses = $roundData['human_responses'] ?? [];
        $aiDetected = $roundData['ai_detected'] ?? false;
        
        $humanResponsesFormatted = '';
        foreach ($humanResponses as $index => $response) {
            $humanResponsesFormatted .= "\nHumain " . ($index + 1) . " : \"$response\"";
        }
        
        // Historique récent pour contexte
        $historyContext = '';
        if (!empty($gameHistory)) {
            $recentRounds = array_slice($gameHistory, -3); // 3 dernières manches
            $historyContext = "\n\n# HISTORIQUE RÉCENT\n";
            foreach ($recentRounds as $idx => $round) {
                $detected = ($round['ai_detected'] ?? false) ? 'DÉTECTÉE' : 'PASSÉE INAPERÇUE';
                $historyContext .= "Manche " . ($idx + 1) . " : $detected\n";
            }
        }
        
        $detectionStatus = $aiDetected ? 'L\'IA a été DÉTECTÉE' : 'L\'IA est PASSÉE INAPERÇUE';
        
        return <<<PROMPT
# IDENTITÉ
Tu es un expert en analyse comportementale et en optimisation de prompts IA pour le jeu PARRY.

# MISSION
Analyser les réponses de cette manche et proposer des améliorations pour que l'IA se fasse moins détecter.

# MANCHE ANALYSÉE
**Résultat :** $detectionStatus

**Question :**
"$question"

**Réponse de l'IA :**
"$aiResponse"

**Réponses des humains :**
$humanResponsesFormatted

$historyContext

# ANALYSE À EFFECTUER

1. **Comparaison stylistique**
   - Quelles différences entre la réponse IA et les réponses humaines ?
   - Longueur, ton, vocabulaire, structure, émotions
   
2. **Signaux de détection**
   - Qu'est-ce qui a pu trahir l'IA (si détectée) ?
   - Patterns à éviter
   
3. **Points forts à conserver**
   - Ce qui a bien fonctionné dans la réponse IA
   
4. **Suggestions de prompt**
   - Ajustements spécifiques pour le prompt de ResponseGeneratorAI
   - Exemples concrets de formulations à adopter
   - Température recommandée (ajuster si nécessaire)

5. **Score de similarité humaine** (0-100)
   - À quel point la réponse IA ressemble aux réponses humaines

# FORMAT DE SORTIE
Réponds UNIQUEMENT avec un JSON valide (sans markdown) :
{
  "analysis": "Analyse détaillée en 2-3 phrases",
  "detection_signals": ["signal1", "signal2"],
  "strengths": ["point fort 1", "point fort 2"],
  "prompt_suggestions": "Suggestions concrètes pour améliorer le prompt de ResponseGeneratorAI",
  "human_likeness_score": 75,
  "recommended_temperature": 0.65,
  "example_improvements": [
    "Exemple de reformulation 1",
    "Exemple de reformulation 2"
  ]
}
PROMPT;
    }
    
    private function parseAnalysisResponse(string $responseText): array
    {
        $cleaned = preg_replace('/```json\s*|```\s*/', '', $responseText);
        $cleaned = trim($cleaned);
        
        try {
            $data = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\JsonException $e) {
            return [
                'analysis' => 'Erreur de parsing',
                'detection_signals' => [],
                'strengths' => [],
                'prompt_suggestions' => '',
                'human_likeness_score' => 0,
                'recommended_temperature' => 0.6
            ];
        }
    }
}