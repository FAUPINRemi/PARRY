<?php

namespace App\Service\AI;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AIImageService
{
    public function __construct(
        private readonly VertexAiImageClient $vertexAiImageClient,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function generateAndSaveAsciiAvatarForUser(User $user): void
    {
        $prompt = $this->buildAsciiAvatarPrompt($user);

        $result = $this->vertexAiImageClient->generateImageBase64([
            'prompt' => $prompt,
            'width' => 96,
            'height' => 96,
            'mime' => 'image/png',
            'numberOfImages' => 1,
            'quality' => 24,
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

        if (strlen($binary) > $maxBytes) {
            throw new \RuntimeException('IMAGE_TROP_GROSSE');
        }

        $user->setAvatarBase64($base64);
        $user->setAvatarMime($mime);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * @return array{binary:string,mime:string}
     */
    private function optimizeTinyAvatar(string $binary, string $mime, int $maxBytes): array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        $targetSize = 64;
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

        imagefilter($target, IMG_FILTER_GRAYSCALE);
        imagefilter($target, IMG_FILTER_CONTRAST, -20);
        imagefilter($target, IMG_FILTER_BRIGHTNESS, 12);
        imagetruecolortopalette($target, false, 4);

        $pngBinary = $this->encodePngToString($target, 9);

        imagedestroy($target);
        imagedestroy($source);

        if ($pngBinary === '' || strlen($pngBinary) > $maxBytes) {
            return ['binary' => $binary, 'mime' => $mime];
        }

        return ['binary' => $pngBinary, 'mime' => 'image/png'];
    }

    private function encodePngToString($image, int $compression): string
    {
        ob_start();
        $encoded = imagepng($image, null, $compression);
        $pngBinary = $encoded ? (string) ob_get_clean() : '';
        if (!$encoded) {
            ob_end_clean();
        }

        return $pngBinary;
    }

    private function buildAsciiAvatarPrompt(User $user): string
    {
        $animal = $this->pickAnimalForUser($user);
        $style = $this->pickStyleForUser($user);

        return implode(' ', [
            'Create a tiny square avatar image in strict terminal ASCII style.',
            'Animal:', $animal . '.',
            'Style direction:', $style . '.',
            'Use only monochrome black and white, no color at all.',
            'White background with dark ASCII glyph-like shapes, centered subject.',
            'ASCII-art inspired look using visible character-like blocks and terminal text texture.',
            'No gradients, no painting effect, no realistic rendering.',
            'Do not generate readable words or letters.',
            'No human face, no gender cues, no realistic portrait. Animal only.',
            'Neutral expression only: no sharp teeth, no fangs, no angry eyebrows, no horror mood.',
            'Simple mascot vibe, clean and balanced.',
            'No watermark, no logo, no signature.',
            'Keep the image extremely simple and high-contrast.',
            'Output should be suitable for a tiny 96x96 avatar and remain readable at small size.',
            'Output must be a PNG image.',
            'Seed hint:', (string) $user->getId(),
        ]);
    }

    private function pickAnimalForUser(User $user): string
    {
        $animals = [
            'fox', 'wolf', 'crow', 'serpent', 'tiger',
            'falcon', 'bear', 'viper', 'lynx', 'puma',
            'eagle', 'shark', 'panther', 'scorpion', 'coyote',
            'owl', 'jaguar', 'raccoon', 'whale', 'vulture',
        ];

        $hash = (int) sprintf('%u', crc32((string) $user->getId()));
        $index = $hash % count($animals);

        return $animals[$index] ?? 'fox';
    }

    private function pickStyleForUser(User $user): string
    {
        $styles = [
            'blocky mascot icon',
            'stencil-like silhouette',
            'badge-style mascot',
            'minimal animal face',
            'geometric animal head',
        ];

        $hash = (int) sprintf('%u', crc32((string) $user->getId() . '-style'));
        $index = $hash % count($styles);

        return $styles[$index] ?? 'blocky mascot icon';
    }
}