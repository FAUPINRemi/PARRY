<?php

namespace App\Controller\Api;

use App\Service\AI\VertexAiTTSService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tts')]
class TTSController extends AbstractController
{
    private const MAX_TEXT_LENGTH = 500;

    public function __construct(
        private readonly VertexAiTTSService $vertexAiTTSService,
    ) {}

    #[Route('', name: 'api_tts', methods: ['POST'])]
    #[OA\Post(path: '/api/tts', summary: 'Synthétiser du texte en audio', tags: ['TTS'])]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'text', type: 'string', description: 'Texte à lire à voix haute (max 500 chars)'),
    ]))]
    #[OA\Response(response: 200, description: 'Audio synthétisé (base64 PCM 16-bit 24kHz mono)')]
    #[OA\Response(response: 400, description: 'Texte manquant ou trop long')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 429, description: 'Budget IA atteint')]
    public function synthesize(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $text = trim($data['text'] ?? '');

        if ($text === '') {
            return $this->json(['success' => false, 'error' => 'TEXTE_MANQUANT'], 400);
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            return $this->json(['success' => false, 'error' => 'TEXTE_TROP_LONG'], 400);
        }

        try {
            $audioBase64 = $this->vertexAiTTSService->synthesize($text);
            return $this->json([
                'success'  => true,
                'audio'    => $audioBase64,
                'mimeType' => 'audio/pcm',
                'sampleRate' => 24000,
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Budget')) {
                return $this->json(['success' => false, 'error' => 'BUDGET_ATTEINT'], 429);
            }
            return $this->json(['success' => false, 'error' => 'ERREUR_SYNTHESE'], 500);
        }
    }
}
