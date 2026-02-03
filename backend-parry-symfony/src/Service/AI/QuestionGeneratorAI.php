<?php

namespace App\Service\AI;

class QuestionGeneratorAI
{
    private const TEMPERATURE = 0.8; 
    private const MAX_TOKENS = 100;
    
    public function __construct(
        private readonly GeminiClient $geminiClient
    ) {}
    
    public function generateQuestion(array $context = []): string
    {
        $prompt = $this->buildPrompt($context);
        
        return $this->geminiClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
    }
    
    private function buildPrompt(array $context): string
    {
        $roundNumber = $context['round_number'] ?? 1;
        $previousQuestions = $context['previous_questions'] ?? [];
        
        $prompt = <<<PROMPT
# IDENTITÉ
Tu es un maître du jeu créatif qui pose une question simple pour le jeu PARRY.

# MISSION
Générer une question originale, simple, courte, engageante et adaptée au contexte du jeu.

# MANIÈRE DE RÉFLÉCHIR
- La question doit susciter des réponses personnelles et variées ou drôles
- Éviter les questions trop factuelles ou avec une seule bonne réponse
- Privilégier les sujets universels (culture, quotidien, opinions, souvenirs, envies)
- Adapter la difficulté au numéro de manche
- Varier les thèmes par rapport aux questions précédentes

# À FAIRE
- Question courte (10-20 mots maximum)
- Ton naturel et accessible
- Poser une vraie question (avec ?)
- Sujets variés : films, nourriture, voyages, enfance, technologie, activité, sport, etc.

# À NE PAS FAIRE
- Questions trop personnelles ou intimes
- Questions nécessitant des connaissances spécialisées
- Questions binaires (oui/non)
- Questions politiques, religieuses ou controversées
- Questions trop longues ou complexes
- Répéter des questions similaires aux précédentes

# CONTEXTE
Manche : $roundNumber
PROMPT;
        
        if (!empty($previousQuestions)) {
            $questionsStr = implode("\n- ", $previousQuestions);
            $prompt .= "\n\nQuestions déjà posées :\n- $questionsStr";
            $prompt .= "\n\nGénère une question DIFFÉRENTE de celles-ci.";
        }
        
        $prompt .= "\n\n# FORMAT DE SORTIE\nUniquement la question, sans guillemets ni préfixe.";
        
        return $prompt;
    }
}