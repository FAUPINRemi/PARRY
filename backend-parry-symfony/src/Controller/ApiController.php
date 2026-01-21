<?php

namespace App\Controller;

use App\Service\TelemetryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private TelemetryService $telemetry
    ) {}

    #[Route('/api/users/{id}', name: 'api_get_user', methods: ['GET'])]
    public function getUserById(int $id): Response  // ← Changé de getUser() à getUserById()
    {
        $startTime = microtime(true);

        // Trace automatique avec gestion d'erreur
        return $this->telemetry->trace('get-user', function ($span) use ($id, $startTime) {
            
            // Ajouter des attributs
            $span->setAttribute('user.id', $id);
            
            // Log
            $this->telemetry->log('info', "Fetching user {$id}");
            
            // Event
            $this->telemetry->addEvent('get-user', 'database.query.start', [
                'query' => 'SELECT * FROM users WHERE id = ?'
            ]);
            
            // Simuler appel DB
            usleep(50000); // 50ms
            
            // Simuler un appel API externe
            $externalStart = microtime(true);
            // ... appel HTTP ...
            $externalDuration = (microtime(true) - $externalStart) * 1000;
            
            $this->telemetry->logExternalApiCall(
                'GET',
                'https://api.example.com/users/' . $id,
                200,
                $externalDuration
            );
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            // Log l'appel API complet
            $this->telemetry->logApiCall('GET', '/api/users/{id}', 200, $duration, [
                'user_id' => $id
            ]);
            
            return $this->json(['id' => $id, 'name' => 'John Doe']);
        });
    }

    #[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
    public function createUser(): Response
    {
        $startTime = microtime(true);

        try {
            $span = $this->telemetry->startSpan('create-user', [
                'operation.type' => 'create'
            ]);

            $this->telemetry->log('info', 'Creating new user');

            // Simuler une erreur
            if (rand(0, 1) === 0) {
                throw new \Exception('User creation failed');
            }

            $this->telemetry->addEvent('create-user', 'user.validated');
            $this->telemetry->addEvent('create-user', 'user.saved');

            $span->end();

            $duration = (microtime(true) - $startTime) * 1000;
            $this->telemetry->logApiCall('POST', '/api/users', 201, $duration);

            return $this->json(['success' => true], 201);

        } catch (\Throwable $e) {
            $this->telemetry->recordError('create-user', $e);
            
            $duration = (microtime(true) - $startTime) * 1000;
            $this->telemetry->logApiCall('POST', '/api/users', 500, $duration);

            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}