<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VertexAiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectId,
        private readonly string $location,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly ?BudgetGuard $budgetGuard = null
    ) {}

    // Appel texte à Vertex AI, bloqué si le budget mensuel est dépassé
    public function generate(
        string $prompt,
        float $temperature = 0.7,
        int $maxTokens = 1024
    ): string {

        $this->logger->warning('[VERTEX_API_OUT] Requête réelle envoyée à Vertex AI.');

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->error('[VERTEX_API_OUT] Requête annulée : BudgetGuard a bloqué l\'appel.');
            throw new \RuntimeException('Budget mensuel atteint.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl(), [
                'json' => [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
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
            $this->logger->critical('[VERTEX_API_OUT] Erreur lors de l\'appel : ' . $e->getMessage());
            throw $e;
        }
    }

    // Transcrit un audio en texte via Vertex AI (STT)
    public function transcribe(string $audioBase64, string $mimeType = 'audio/webm'): string
    {
        $this->logger->warning('[VERTEX_API_OUT] Transcription audio envoyée à Vertex AI.');

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->error('[VERTEX_API_OUT] Transcription annulée : BudgetGuard a bloqué l\'appel.');
            throw new \RuntimeException('Budget mensuel atteint.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl(), [
                'json' => [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $audioBase64,
                                ],
                            ],
                            [
                                'text' => 'Transcris exactement ce qui est dit dans cet audio en français. Retourne uniquement le texte transcrit, sans ponctuation excessive, sans guillemets, sans explication.',
                            ],
                        ],
                    ]],
                ],
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30,
            ]);

            $data   = $response->toArray();
            $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if ($this->budgetGuard) {
                $this->budgetGuard->trackRequest($this->estimateAudioCost($audioBase64, $result));
            }

            return trim($result);

        } catch (\Exception $e) {
            $this->logger->critical('[VERTEX_API_OUT] Erreur transcription : ' . $e->getMessage());
            throw $e;
        }
    }

    private function buildUrl(): string
    {
        return sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent?key=%s',
            $this->location,
            $this->projectId,
            $this->location,
            $this->model,
            $this->apiKey
        );
    }

    private function estimateCost(string $prompt, string $response): float
    {
        $inputTokens = strlen($prompt) / 4;
        $outputTokens = strlen($response) / 4;
        return (($inputTokens / 1_000_000) * 0.09) + (($outputTokens / 1_000_000) * 0.36);
    }

    private function estimateAudioCost(string $audioBase64, string $textOutput): float
    {
        $durationSeconds = (strlen($audioBase64) * 0.75) / 200;
        $audioTokens     = $durationSeconds * 32;
        $outputTokens    = strlen($textOutput) / 4;
        return ($audioTokens / 1_000_000 * 0.30) + ($outputTokens / 1_000_000 * 0.36);
    }
}
