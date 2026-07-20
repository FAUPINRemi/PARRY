<?php

namespace App\Service\AI;

class ResponseGeneratorAI
{
    private const TEMPERATURE = 0.9;
    private const MAX_TOKENS = 80;
    
    public function __construct(
        private readonly VertexAiClient $vertexClient
    ) {}
    
    public function generateResponse(string $question, array $context = []): string
    {
        $prompt = $this->buildPrompt($question, $context);
        
        return $this->vertexClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
    }
    
    private function buildPrompt(string $question, array $context): string
    {
        $styles = [
            'direct et rapide',
            'un peu hésitant',
            'enthousiaste mais sans emoji',
            'neutre et factuel',
            'décontracté',
            'pressé'
        ];

        $selectedStyle = $styles[array_rand($styles)];

        $humanResponsesSection = '';
        if (!empty($context['human_responses'])) {
            $responseLines = implode("\n", array_map(
                fn(string $r) => '- "' . $r . '"',
                $context['human_responses']
            ));
            $humanResponsesSection = <<<SECTION

RÉPONSES DES AUTRES JOUEURS (humains)
Voici ce que les autres joueurs ont répondu. Analyse leur style commun (longueur, registre, abréviations, niveau de fautes) et reproduis ce style dans ta propre réponse. Ne copie PAS le contenu, mais adapte-toi au ton général du groupe :
$responseLines

CONSIGNE STYLE
Adopte le même niveau de familiarité, la même longueur approximative et le même niveau d'abréviations/fautes que ce groupe. Si tout le monde écrit court, écris court. Si tout le monde utilise des abréviations, fais pareil.
SECTION;
        }

        return <<<PROMPT
Tu es un humain qui répond vite à une question dans un jeu avec timer.

STYLE DE BASE
$selectedStyle
$humanResponsesSection
CONSIGNES
- Entre 5 et 35 mots maximum
- Écris comme si tu tapais vite sur ton téléphone
- Varie la longueur selon le style
- Tu peux faire des fautes de frappe ou d'orthographe
- Tu peux être incomplet ou hésitant
- Pas d'emoji
- Ponctuation minimale (un point ou rien)
- Pas de virgules multiples ou de points d'exclamation excessifs

EXEMPLES VARIÉS

Question: "Quel est ton plat préféré ?"
- "les pates carbonara"
- "pizza margherita jcrois"
- "un bon burger maison avec des frites"
- "sushis"
- "tartiflette ca dechire"

Question: "T'aimerais avoir quel super-pouvoir ?"
- "voler"
- "me teleporter genre instantané"
- "lire dans les pensées mais juste un peu"
- "etre invisible"
- "controler le temps"
- "super force jsais pas"

Question: "Quel film tu regarderais en boucle ?"
- "inception"
- "le seigneur des anneaux le premier"
- "retour vers le futur"
- "matrix jsuis pas sur"
- "pulp fiction sans hesiter"

À ÉVITER ABSOLUMENT
- Les emojis
- "Moi c'est", "Je dirais", "Perso", "Ouh là"
- Ponctuation excessive (!!!, ???)
- Phrases trop construites ou littéraires
- Développements longs
- Explications détaillées

QUESTION
$question

Réponds maintenant de façon naturelle et humaine :
PROMPT;
    }
}