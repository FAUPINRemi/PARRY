<?php

namespace App\Controller\Api;

use App\Service\AI\VertexAiClient;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stt')]
class STTController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'audio/webm',
        'audio/wav',
        'audio/ogg',
        'audio/mp3',
        'audio/flac',
        'audio/aac',
    ];

    private const MAX_AUDIO_BASE64_LENGTH = 2_097_152;

    public function __construct(
        private readonly VertexAiClient $vertexClient,
    ) {}

    #[Route('', name: 'api_stt', methods: ['POST'])]
    #[OA\Post(path: '/api/stt', summary: 'Transcrire un audio en texte', tags: ['STT'])]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'audio', type: 'string', description: 'Audio encodé en base64'),
        new OA\Property(property: 'mimeType', type: 'string', description: 'Type MIME audio (audio/webm, audio/ogg…)'),
    ]))]
    #[OA\Response(response: 200, description: 'Transcription réussie')]
    #[OA\Response(response: 400, description: 'Données manquantes ou invalides')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 429, description: 'Budget IA atteint')]
    public function transcribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'NON_AUTHENTIFIE'], 401);
        }

        $data     = json_decode($request->getContent(), true);
        $audio    = $data['audio'] ?? null;
        $mimeType = $data['mimeType'] ?? 'audio/webm';

        if (!$audio) {
            return $this->json(['success' => false, 'error' => 'DONNEES_MANQUANTES'], 400);
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->json(['success' => false, 'error' => 'MIME_TYPE_INVALIDE'], 400);
        }

        if (strlen($audio) > self::MAX_AUDIO_BASE64_LENGTH) {
            return $this->json(['success' => false, 'error' => 'AUDIO_TROP_LONG'], 400);
        }

        try {
            $text = $this->vertexClient->transcribe($audio, $mimeType);
            return $this->json(['success' => true, 'text' => $text]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Budget')) {
                return $this->json(['success' => false, 'error' => 'BUDGET_ATTEINT'], 429);
            }
            return $this->json(['success' => false, 'error' => 'ERREUR_TRANSCRIPTION'], 500);
        }
    }
}
