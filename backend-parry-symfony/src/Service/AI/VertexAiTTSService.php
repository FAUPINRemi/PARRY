<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VertexAiTTSService
{
    // Voix disponibles : Aoede, Charon, Fenrir, Kore, Puck
    private const VOICE_NAME = 'Charon';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectId,
        private readonly string $location,
        private readonly string $ttsModel,
        private readonly string $apiKey,
        private readonly ?BudgetGuard $budgetGuard = null
    ) {}

    /**
     * Synthétise du texte en audio PCM 16-bit 24kHz mono.
     * Retourne le contenu audio encodé en base64.
     */
    public function synthesize(string $text): string
    {
        $this->logger->warning('[VERTEX_TTS_OUT] Synthèse vocale envoyée à Vertex AI.');

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->error('[VERTEX_TTS_OUT] Synthèse annulée : BudgetGuard a bloqué l\'appel.');
            throw new \RuntimeException('Budget mensuel atteint.');
        }

        try {
            $url = sprintf(
                'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent?key=%s',
                $this->location,
                $this->projectId,
                $this->location,
                $this->ttsModel,
                $this->apiKey
            );

            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $text]],
                    ]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => [
                            'voiceConfig' => [
                                'prebuiltVoiceConfig' => [
                                    'voiceName' => self::VOICE_NAME,
                                ],
                            ],
                        ],
                    ],
                ],
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $audioBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? '';

            if ($audioBase64 === '') {
                throw new \RuntimeException('Réponse TTS vide de l\'API Vertex AI.');
            }

            if ($this->budgetGuard) {
                $this->budgetGuard->trackRequest($this->estimateCost($text));
            }

            return $audioBase64;

        } catch (\Exception $e) {
            $this->logger->critical('[VERTEX_TTS_OUT] Erreur synthèse vocale : ' . $e->getMessage());
            throw $e;
        }
    }

    // ~$0.50 / 1M chars d'entrée (tarif Flash TTS)
    private function estimateCost(string $text): float
    {
        return (strlen($text) / 1_000_000) * 0.50;
    }
}
