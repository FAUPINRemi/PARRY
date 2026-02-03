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
        private readonly string $model
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
            
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de l\'appel à Gemini API: ' . $e->getMessage());
        }
    }
}