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

        $response = $this->vertexClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );

        return $this->sanitize($response);
    }

    // Filet de sécurité : le prompt interdit déjà guillemets/emojis, mais le LLM peut dévier
    private function sanitize(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^["“\'](.*)["”\']$/us', '$1', $text) ?? $text;
        $text = preg_replace('/[\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', $text) ?? $text;
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;

        return trim($text);
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
                fn(string $r) => '- ' . $r,
                $context['human_responses']
            ));
            $humanResponsesSection = <<<SECTION

RÉPONSES DES AUTRES JOUEURS (humains)
Voici ce que les autres joueurs ont répondu (sans guillemets, c'est le texte brut qu'ils ont tapé). Analyse leur style commun (longueur, registre, abréviations, niveau de fautes, présence ou non d'emoji) et reproduis ce style dans ta propre réponse. Ne copie PAS le contenu, mais adapte-toi au ton général du groupe :
$responseLines

CONSIGNE STYLE (PRIORITAIRE)
C'est la consigne la plus importante : adopte le même niveau de familiarité, la même longueur approximative et le même niveau d'abréviations/fautes que ce groupe, plutôt que le style de base ci-dessus. Si tout le monde écrit court, écris court. Si tout le monde utilise des abréviations, fais pareil. Si personne n'utilise d'emoji, n'en mets surtout pas.
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
- Ne mets JAMAIS ta réponse entre guillemets, les exemples ci-dessous sont juste une liste à puces

EXEMPLES VARIÉS (le tiret est juste une puce de liste, ne l'inclus pas et ne mets pas de guillemets)

Question: Quel est ton plat préféré ?
- les pates carbonara
- pizza margherita jcrois
- un bon burger maison avec des frites
- sushis
- tartiflette ca dechire

Question: T'aimerais avoir quel super-pouvoir ?
- voler
- me teleporter genre instantané
- lire dans les pensées mais juste un peu
- etre invisible
- controler le temps
- super force jsais pas

Question: Quel film tu regarderais en boucle ?
- inception
- le seigneur des anneaux le premier
- retour vers le futur
- matrix jsuis pas sur
- pulp fiction sans hesiter

À ÉVITER ABSOLUMENT
- Les emojis
- Les guillemets autour de la réponse
- "Moi c'est", "Je dirais", "Perso", "Ouh là"
- Ponctuation excessive (!!!, ???)
- Phrases trop construites ou littéraires
- Développements longs
- Explications détaillées

QUESTION
$question

Réponds maintenant de façon naturelle et humaine, en texte brut sans guillemets autour :
PROMPT;
    }
}