<?php

namespace App\Service\AI;

use App\Repository\QuestionCategoryRepository;

class QuestionGeneratorAI
{
    private const TEMPERATURE = 0.8;
    private const MAX_TOKENS = 100;

    public function __construct(
        private readonly VertexAiClient $vertexClient,
        private readonly QuestionCategoryRepository $categoryRepository
    ) {}

    public function generateQuestion(array $context = []): string
    {
        $prompt = $this->buildPrompt($context);
        
        return $this->vertexClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
    }

    private function buildPrompt(array $context): string
    {
        $roundNumber = $context['round_number'] ?? 1;
        $previousQuestions = $context['previous_questions'] ?? [];
        
        // Récupération d'une catégorie aléatoire depuis la base
        $category = $this->categoryRepository->findRandomActive();
        
        if ($category === null) {
            throw new \RuntimeException('Aucune catégorie active trouvée en base de données');
        }
        
        $selectedCategory = $category->getName();
        
        $prompt = <<<PROMPT
# IDENTITÉ
Tu es un maître du jeu créatif qui pose une question simple pour le jeu PARRY.

# MISSION
Générer une question originale, simple, courte, engageante sur le thème imposé.

# THÈME IMPOSÉ POUR CETTE QUESTION
$selectedCategory

Tu DOIS poser une question en lien avec ce thème uniquement.

# MANIÈRE DE RÉFLÉCHIR
- La question doit susciter des réponses personnelles et variées ou drôles
- Éviter les questions trop factuelles ou avec une seule bonne réponse
- Rester dans le thème imposé
- Adapter la difficulté au numéro de manche

# À FAIRE
- Question courte (10-20 mots maximum)
- Ton naturel et accessible
- Poser une vraie question (avec ?)
- Respecter strictement le thème imposé

# À NE PAS FAIRE
- Sortir du thème imposé
- Questions trop personnelles ou intimes
- Questions nécessitant des connaissances spécialisées
- Questions binaires (oui/non)
- Questions politiques, religieuses ou controversées
- Questions trop longues ou complexes

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