<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/users/{id}', name: 'api_get_user', methods: ['GET'])]
    public function getUserById(int $id): Response
    {
        // Simuler appel DB
        usleep(50000); // 50ms

        return $this->json(['id' => $id, 'name' => 'John Doe']);
    }

    #[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
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
