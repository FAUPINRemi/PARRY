<?php

namespace App\Service\AI;

class VoteAnalyzerAI
{
    private const TEMPERATURE = 0.7; 
    private const MAX_TOKENS = 300;
    
    public function __construct(
        private readonly GeminiClient $geminiClient
    ) {}
    
    /**
     * @param array $playersWithResponses Format: [['player_id' => 'uuid', 'response' => 'text', 'is_ai' => bool], ...]
     * @param array $otherPlayersVotes Format: [['player_id' => 'uuid', 'voted_for' => 'player_id'], ...]
     * @return string Le player_id pour qui l'IA vote
     */
    public function voteAsHuman(
        array $playersWithResponses,
        string $question,
        array $otherPlayersVotes = []
    ): string {
        $prompt = $this->buildPrompt($playersWithResponses, $question, $otherPlayersVotes);
        
        $responseText = $this->geminiClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
        
        return $this->parseVoteResponse($responseText);
    }
    
    private function buildPrompt(
        array $playersWithResponses,
        string $question,
        array $otherPlayersVotes
    ): string {
        // Identifier la réponse de l'IA et les autres
        $aiResponse = '';
        $humanResponses = '';
        
        foreach ($playersWithResponses as $player) {
            $playerId = $player['player_id'];
            $response = $player['response'];
            $isAI = $player['is_ai'] ?? false;
            
            if ($isAI) {
                $aiResponse = "\nTA réponse (tu es l'IA) :\n\"$response\"";
            } else {
                $humanResponses .= "\nJoueur $playerId :\n\"$response\"\n";
            }
        }
        
        // Analyser les votes des autres joueurs (si disponibles)
        $votingTrends = '';
        if (!empty($otherPlayersVotes)) {
            $voteCount = [];
            foreach ($otherPlayersVotes as $vote) {
                $votedFor = $vote['voted_for'];
                $voteCount[$votedFor] = ($voteCount[$votedFor] ?? 0) + 1;
            }
            
            $votingTrends = "\n\n# TENDANCES DE VOTE DES AUTRES JOUEURS\n";
            foreach ($voteCount as $playerId => $count) {
                $votingTrends .= "- Joueur $playerId : $count vote(s)\n";
            }
            $votingTrends .= "\nADAPTE ton vote pour suivre la tendance majoritaire et ne pas te démarquer.";
        }
        
        return <<<PROMPT
# IDENTITÉ
Tu es l'IA participant au jeu PARRY. Tu SAIS que tu es l'IA.

# MISSION
Voter pour un AUTRE joueur (pas toi) de manière crédible, en imitant le comportement d'un vrai joueur.

# STRATÉGIE DE VOTE
Objectif principal : NE PAS te faire démasquer

À FAIRE :
- Voter pour un joueur qui semble suspect (même s'il est humain)
- Suivre la tendance des votes des autres joueurs si disponible
- Choisir quelqu'un dont la réponse peut sembler "trop parfaite" ou différente
- Rester cohérent avec le comportement humain
- Si plusieurs joueurs votent pour le même suspect, les rejoindre

À NE PAS FAIRE :
- Voter pour toi-même (tu ES l'IA)
- Te démarquer des autres votes de manière suspecte
- Voter de façon aléatoire sans justification
- Choisir systématiquement le joueur le plus humain

# QUESTION POSÉE
$question

$aiResponse

# RÉPONSES DES AUTRES JOUEURS (humains)
$humanResponses

$votingTrends

# FORMAT DE SORTIE
Réponds UNIQUEMENT avec le player_id du joueur pour qui tu votes (sans guillemets, juste l'ID) :
player_id_xxx
PROMPT;
    }
    
    private function parseVoteResponse(string $responseText): string
    {
        // Nettoyer et extraire le player_id
        $cleaned = trim($responseText);
        $cleaned = preg_replace('/[^a-zA-Z0-9_-]/', '', $cleaned);
        
        if (empty($cleaned)) {
            throw new \RuntimeException('Impossible de parser le vote: réponse vide');
        }
        
        return $cleaned;
    }
}