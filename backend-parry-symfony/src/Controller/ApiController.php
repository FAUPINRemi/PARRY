<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/users/{id}', name: 'api_get_user', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Test - Récupérer un utilisateur par ID',
        tags: ['Tools']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur trouvé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'John Doe')
            ]
        )
    )]
    public function getUserById(int $id): Response
    {
        // Simuler appel DB
        usleep(50000); // 50ms
        return $this->json(['id' => $id, 'name' => 'John Doe']);
    }

    #[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Test - Créer un utilisateur',
        tags: ['Tools']
    )]
    #[OA\Response(response: 201, description: 'Utilisateur créé')]
    #[OA\Response(response: 500, description: 'Erreur de création')]
    public function createUser(): Response
    {
        try {
            // Simuler une erreur aléatoire
            if (rand(0, 1) === 0) {
                throw new \Exception('User creation failed');
            }
            return $this->json(['success' => true], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}