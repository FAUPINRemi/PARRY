<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeminiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?BudgetGuard $budgetGuard = null 
    ) {}

    /**
     * Génère une réponse avec Gemini
     * 
     * @param string $prompt Le prompt système et utilisateur
     * @param float $temperature Contrôle la créativité (0.0 à 2.0)
     * @param int $maxTokens Limite de tokens en sortie
     * @return string La réponse générée
     */
    public function generate(
        string $prompt,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string {
        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            throw new \RuntimeException('Budget mensuel de 5€ atteint. Les appels Gemini sont bloqués jusqu\'au mois prochain.');
        }

        try {
            $response = $this->httpClient->request('POST', 
                $this->apiUrl . $this->model . ':generateContent?key=' . $this->apiKey,
                [
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => $temperature,
                            'maxOutputTokens' => $maxTokens,
                        ]
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ]
                ]
            );
            
            $data = $response->toArray();
            
            $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if ($this->budgetGuard) {
                $estimatedCost = $this->estimateCost($prompt, $result);
                $this->budgetGuard->trackRequest($estimatedCost);
            }
            
            return $result;
            
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de l\'appel à Gemini API: ' . $e->getMessage());
        }
    }

    /**
     * Estime le coût d'une requête Gemini
     * Gemini 2.0 Flash : ~0.09€ / 1M tokens input, ~0.36€ / 1M tokens output
     */
    private function estimateCost(string $prompt, string $response): float
    {
        $inputTokens = strlen($prompt) / 4;
        $outputTokens = strlen($response) / 4;
        
        // Prix Gemini 2.5 Flash-light
        $inputCost = ($inputTokens / 1_000_000) * 0.09;   
        $outputCost = ($outputTokens / 1_000_000) * 0.36;  
        
        return $inputCost + $outputCost;
    }
}