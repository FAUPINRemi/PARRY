<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeminiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?BudgetGuard $budgetGuard = null 
    ) {}

    /**
     * Génère une réponse avec Gemini
     */
    public function generate(
        string $prompt,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string {
        // Log pour tracer l'origine de l'appel dans var/log/dev.log
        $this->logger->info('Gemini AI: Tentative de génération demandée.', [
            'model' => $this->model,
            'prompt_preview' => substr($prompt, 0, 50) . '...',
            'temperature' => $temperature
        ]);

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->warning('Gemini AI: Requête bloquée par le BudgetGuard (limite atteinte).');
            throw new \RuntimeException('Budget mensuel de 5€ atteint. Les appels sont bloqués.');
        }

        try {
            // Construction de l'URL avec la clé API
            $url = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

            $response = $this->httpClient->request('POST', $url, [
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
            ]);
            
            $data = $response->toArray();
            
            // Extraction sécurisée du texte
            $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if (empty($result)) {
                $this->logger->error('Gemini AI: Réponse vide reçue de l\'API.', ['raw_data' => $data]);
            } else {
                $this->logger->info('Gemini AI: Réponse générée avec succès.', [
                    'char_count' => strlen($result)
                ]);
            }

            // Mise à jour du budget local
            if ($this->budgetGuard) {
                $estimatedCost = $this->estimateCost($prompt, $result);
                $this->budgetGuard->trackRequest($estimatedCost);
            }
            
            return $result;
            
        } catch (TransportExceptionInterface $e) {
            $this->logger->critical('Gemini AI: Erreur de transport (réseau).', [
                'message' => $e->getMessage()
            ]);
            throw new \RuntimeException('Erreur lors de l\'appel à Gemini API: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Gemini AI: Erreur inattendue.', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Estime le coût (Gemini 2.0 Flash)
     */
    private function estimateCost(string $prompt, string $response): float
    {
        // Estimation simple : 4 caractères ~= 1 token
        $inputTokens = strlen($prompt) / 4;
        $outputTokens = strlen($response) / 4;
        
        // Tarifs approximatifs pour 1M de tokens
        $inputCost = ($inputTokens / 1_000_000) * 0.09;   
        $outputCost = ($outputTokens / 1_000_000) * 0.36;  
        
        return $inputCost + $outputCost;
    }
}