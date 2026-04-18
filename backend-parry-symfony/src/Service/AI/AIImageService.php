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
        $this->logger->info('[AI_IMAGE] Debut generation avatar', [
            'userId' => (string) $user->getId(),
        ]);

        $prompt = $this->buildAsciiAvatarPrompt($user);

        $result = $this->vertexAiImageClient->generateImageBase64([
            'prompt' => $prompt,
            'width' => 48,
            'height' => 48,
            'mime' => 'image/jpeg',
            'numberOfImages' => 1,
            'quality' => 10,
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

        $maxBytes = 120_000;
        $optimized = $this->optimizeTinyAvatar($binary, $mime, $maxBytes);
        $binary = $optimized['binary'];
        $mime = $optimized['mime'];
        $base64 = base64_encode($binary);

        $this->logger->info('[AI_IMAGE] Avatar optimise', [
            'userId' => (string) $user->getId(),
            'mime' => $mime,
            'bytes' => strlen($binary),
        ]);

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

        $this->logger->info('[AI_IMAGE] Avatar enregistre en base', [
            'userId' => (string) $user->getId(),
            'mime' => $mime,
            'base64Length' => strlen($base64),
        ]);
    }

    /**
     * @return array{binary:string,mime:string}
     */
    private function optimizeTinyAvatar(string $binary, string $mime, int $maxBytes): array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        $targetSize = 32;
        $target = imagecreatetruecolor($targetSize, $targetSize);
        if ($target === false) {
            imagedestroy($source);
            return ['binary' => $binary, 'mime' => $mime];
        }

        imagefilledrectangle($target, 0, 0, $targetSize, $targetSize, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetSize,
            $targetSize,
            imagesx($source),
            imagesy($source)
        );

        $jpegBinary = $this->encodeJpegToString($target, 8);

        imagedestroy($target);
        imagedestroy($source);

        if ($jpegBinary === '' || strlen($jpegBinary) > $maxBytes) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        return ['binary' => $jpegBinary, 'mime' => 'image/jpeg'];
    }

    private function encodeJpegToString($image, int $quality): string
    {
        ob_start();
        $encoded = imagejpeg($image, null, $quality);
        $jpegBinary = $encoded ? (string) ob_get_clean() : '';
        if (!$encoded) {
            ob_end_clean();
        }

        return $jpegBinary;
    }

    private function buildAsciiAvatarPrompt(User $user): string
    {

        return implode(' ', [
            'Create a tiny square black-and-white profile avatar image (headshot silhouette).',
            'Minimalist ASCII-art inspired look using large monospace character blocks, with clean shapes and very low detail.',
            'Plain white background. Centered subject.',
            'No dithering, no noise texture, no gradients, no complex shading.',
            'Do not generate any readable words or letters, only abstract character-like blocks.',
            'No watermark, no logo, no signature.',
            'Keep the image extremely simple and high-contrast.',
            'Output should be suitable for a tiny 48x48 avatar.',
            'Output must be a JPEG image.',
            'Seed hint:', (string) $user->getId(),
        ]);
    }
}