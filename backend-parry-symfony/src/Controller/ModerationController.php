<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ModerationController extends AbstractController
{
    #[Route('/api/moderation', name: 'api_moderation', methods: ['POST'])]
    public function moderate(Request $request): JsonResponse
    {
        // TODO: Ajouter la logique de modération
        return $this->json([
            'moderation' => 'Résultat de la modération',
        ]);
    }
}
