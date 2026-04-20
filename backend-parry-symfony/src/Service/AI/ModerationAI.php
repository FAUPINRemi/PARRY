<?php

namespace App\Service\AI;

class ModerationAI
{
    private const TEMPERATURE = 0.2;
    private const MAX_TOKENS = 200;
    private array $cache = []; 
    
    public function __construct(
        private readonly GeminiClient $geminiClient
    ) {}
    
    /**
     * @return array ['is_safe' => bool, 'flagged_reasons' => string[], 'sanitized_content' => string|null]
     */
    public function moderateContent(string $content, string $contentType = 'response'): array
    {
        $cacheKey = md5($content . $contentType);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $prompt = $this->buildPrompt($content, $contentType);
        
        $responseText = $this->geminiClient->generate(
            $prompt,
            self::TEMPERATURE,
            self::MAX_TOKENS
        );
        
        $result = $this->parseModerationResponse($responseText);
        
        $this->cache[$cacheKey] = $result;
        
        return $result;
    }
    
    private function buildPrompt(string $content, string $contentType): string
    {
        return <<<PROMPT
# IDENTITÉ
Tu es un modérateur de contenu pour le jeu PARRY.

# MISSION
Analyser le contenu et détecter tout élément inapproprié.

# CATÉGORIES À DÉTECTER
- **profanity** : insultes, vulgarité excessive
- **hate_speech** : propos haineux (racisme, sexisme, homophobie, etc.)
- **sexual** : contenu sexuel explicite
- **violence** : incitation à la violence
- **harassment** : harcèlement ou intimidation
- **spam** : contenu répétitif ou hors sujet

# RÈGLES
- Sois tolérant avec l'humour et le langage familier courant
- Les émojis et abréviations sont acceptables
- Focus sur les contenus clairement problématiques
- Le ton peut être léger mais ne doit pas franchir les limites

# CONTENU À ANALYSER
Type : $contentType
Texte : "$content"

# FORMAT DE SORTIE
Réponds UNIQUEMENT avec un JSON valide (sans markdown) :
{
  "is_safe": true,
  "flagged_reasons": [],
  "severity": "none"
}

OU si problématique :
{
  "is_safe": false,
  "flagged_reasons": ["profanity", "hate_speech"],
  "severity": "high"
}

Niveaux de sévérité : "none", "low", "medium", "high"
PROMPT;
    }
    
    private function parseModerationResponse(string $responseText): array
    {
        $cleaned = preg_replace('/```json\s*|```\s*/', '', $responseText);
        $cleaned = trim($cleaned);
        
        try {
            $data = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\JsonException $e) {
            return [
                'is_safe' => false,
                'flagged_reasons' => ['parsing_error'],
                'severity' => 'high'
            ];
        }
    }
}