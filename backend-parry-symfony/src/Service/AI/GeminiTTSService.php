<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiTTSService
{
    // Voix disponibles : Aoede, Charon, Fenrir, Kore, Puck
    private const VOICE_NAME = 'Charon';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $ttsModel,
        private readonly ?BudgetGuard $budgetGuard = null
    ) {}

    /**
     * Synthétise du texte en audio PCM 16-bit 24kHz mono.
     * Retourne le contenu audio encodé en base64.
     */
    public function synthesize(string $text): string
    {
        $this->logger->warning('[GEMINI_TTS_OUT] Synthèse vocale envoyée à Google AI Studio.');

        if ($this->budgetGuard && !$this->budgetGuard->canMakeRequest()) {
            $this->logger->error('[GEMINI_TTS_OUT] Synthèse annulée : BudgetGuard a bloqué l\'appel.');
            throw new \RuntimeException('Budget mensuel atteint.');
        }

        try {
            $url = rtrim($this->apiUrl, '/') . '/' . $this->ttsModel . ':generateContent?key=' . $this->apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [[
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
                throw new \RuntimeException('Réponse TTS vide de l\'API Gemini.');
            }

            if ($this->budgetGuard) {
                $this->budgetGuard->trackRequest($this->estimateCost($text));
            }

            return $audioBase64;

        } catch (\Exception $e) {
            $this->logger->critical('[GEMINI_TTS_OUT] Erreur synthèse vocale : ' . $e->getMessage());
            throw $e;
        }
    }

    // ~$0.50 / 1M chars d'entrée (tarif Flash TTS)
    private function estimateCost(string $text): float
    {
        return (strlen($text) / 1_000_000) * 0.50;
    }
}
