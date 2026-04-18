<?php

namespace App\Service\AI;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AIImageService
{
    public function __construct(
        private readonly VertexAiImageClient $vertexAiImageClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function generateAndSaveAsciiAvatarForUser(User $user): void
    {
        $prompt = $this->buildAsciiAvatarPrompt($user);

        $result = $this->vertexAiImageClient->generateImageBase64([
            'prompt' => $prompt,
            'width' => 512,
            'height' => 512,
            'mime' => 'image/jpeg',
            'numberOfImages' => 1,
        ]);

        $base64 = $result['base64'] ?? null;
        $mime = $result['mime'] ?? 'image/jpeg';

        if (!$base64 || !is_string($base64)) {
            throw new \RuntimeException('IMAGE_BASE64_VIDE');
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw new \RuntimeException('IMAGE_BASE64_INVALIDE');
        }

        $maxBytes = 700_000;
        if (strlen($binary) > $maxBytes) {
            $this->logger->warning('[AI_IMAGE] Image trop grosse', [
                'bytes' => strlen($binary),
                'userId' => (string) $user->getId(),
                'mime' => $mime,
            ]);
            throw new \RuntimeException('IMAGE_TROP_GROSSE');
        }

        $user->setAvatarBase64($base64);
        $user->setAvatarMime($mime);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function buildAsciiAvatarPrompt(User $user): string
    {

        return implode(' ', [
            'Create a square black-and-white profile avatar image (headshot silhouette).',
            'Minimalist ASCII-art inspired look using large monospace character blocks, with clean shapes and very low detail.',
            'Plain white background. Centered subject.',
            'No dithering, no noise texture, no gradients, no complex shading.',
            'Do not generate any readable words or letters, only abstract character-like blocks.',
            'No watermark, no logo, no signature.',
            'Keep the image extremely simple and high-contrast.',
            'Output must be a JPEG image.',
            'Seed hint:', (string) $user->getId(),
        ]);
    }
}