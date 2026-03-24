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

    public function generate(
        string $prompt,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string {
        
        // Log de surveillance immédiat
        $this->logger->warning('[GEMINI_API_OUT] Requête réelle envoyée à Google AI Studio.');

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->error('[GEMINI_API_OUT] Requête annulée : BudgetGuard a bloqué l\'appel.');
            throw new \RuntimeException('Budget mensuel atteint.');
        }

        try {
            $url = rtrim($this->apiUrl, '/') . '/' . $this->model . ':generateContent?key=' . $this->apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'maxOutputTokens' => $maxTokens,
                    ]
                ],
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 20,
            ]);
            
            $data = $response->toArray();
            $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if ($this->budgetGuard) {
                $this->budgetGuard->trackRequest($this->estimateCost($prompt, $result));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->critical('[GEMINI_API_OUT] Erreur lors de l\'appel : ' . $e->getMessage());
            throw $e;
        }
    }

    private function estimateCost(string $prompt, string $response): float
    {
        $inputTokens = strlen($prompt) / 4;
        $outputTokens = strlen($response) / 4;
        return (($inputTokens / 1_000_000) * 0.09) + (($outputTokens / 1_000_000) * 0.36);
    }
}