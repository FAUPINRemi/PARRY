<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use App\Service\AI\AIImageService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/ai')]
class AIProfileImageController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AIImageService $aiImageService,
    ) {}

    #[Route('/users/{userId}/profile-image', name: 'api_ai_generate_profile_image', methods: ['POST'])]
    #[OA\Post(
        path: '/api/ai/users/{userId}/profile-image',
        summary: "Génère une image de profil (ASCII N&B) et l'enregistre en base64 en BDD",
        tags: ['AI']
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        required: true,
        description: "UUID de l'utilisateur",
        schema: new OA\Schema(type: 'string', example: 'b5c9b4c6-8f7c-4b5e-9c47-8f8a2c3d4e5f')
    )]
    #[OA\Response(
        response: 200,
        description: 'Avatar généré et enregistré',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Avatar généré'),
            ]
        )
    )]
    public function generateProfileImage(string $userId): JsonResponse
    {
        if (!Uuid::isValid($userId)) {
            return $this->json(
                ['success' => false, 'error' => 'USER_ID_INVALIDE'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->find(Uuid::fromString($userId));
        if (!$user) {
            return $this->json(
                ['success' => false, 'error' => 'UTILISATEUR_INTROUVABLE'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->aiImageService->generateAndSaveAsciiAvatarForUser($user);

            return $this->json([
                'success' => true,
                'message' => 'Avatar généré',
                // si tu veux renvoyer direct le data URL :
                'avatar' => $user->getAvatarDataUrl(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(
                ['success' => false, 'error' => 'ERREUR_GENERATION_AVATAR', 'details' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}