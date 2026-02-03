<?php

namespace App\Service\AI;

class ResponseGeneratorAI
{
    private const TEMPERATURE = 0.7;
    private const MAX_TOKENS = 60;
    
    public function __construct(
        private readonly GeminiClient $geminiClient
    ) {}
    
    public function generateResponse(string $question, array $context = []): string
    {
        $prompt = $this->buildPrompt($question, $context);
        
        return $this->geminiClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
    }
    
    private function buildPrompt(string $question, array $context): string
    {
        return <<<PROMPT
Tu es un joueur qui répond vite à une question de jeu entre potes.

RÈGLES :
- Maximum 20-30 mots (2-3 phrases courtes max)
- Style SMS/chat : direct, spontané
- Une seule idée principale
- Pas de développement, pas d'explication longue
- Comme si tu répondais rapidement sur ton téléphone
- Fautes d'orthographe et de grammaire acceptables

EXEMPLES DE  RÉPONSES :
Question: "Quel est ton plat préféré ?"
"Les sushi"
"Des pate pesto"
"Les pâtes carbo"

Question: "T'aimerais avoir quel super-pouvoir ?"
"Me tp"
"Voler"
"Lire dan s les pensées"
" voyager dans le temps"

À ÉVITER :
Trop de détails ou d'exemples
Plusieurs arguments enchaînés
"Ouh là", "question piège", "imagine", "Moi c'est", "Je dirais", "Perso" ...
Développements type "et puis, et aussi, en plus..."
Les virugules, smileys ou emojis, la politesse excessive, la ponctuation lourde, la ponctuation exagérée (!!! ???), la ponctuation

QUESTION : $question

Réponds direct, court et naturel :
PROMPT;
    }
}