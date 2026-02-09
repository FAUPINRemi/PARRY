<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DatabaseTestController extends AbstractController
{
    public function __construct(
        private Connection $connection
    ) {}

    #[Route('/api/test-db', name: 'api_test_db', methods: ['GET'])]
    #[OA\Get(
        path: '/api/test-db',
        summary: 'Tester la connexion à la base de données',
        tags: ['Tools']
    )]
    #[OA\Response(
        response: 200,
        description: 'Statistiques de la base de données',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'version', type: 'string'),
                new OA\Property(property: 'tables_count', type: 'integer'),
                new OA\Property(
                    property: 'stats',
                    properties: [
                        new OA\Property(property: 'tables_count', type: 'integer'),
                        new OA\Property(property: 'db_size', type: 'integer')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function testDatabase(): Response
    {
        // Query 1 - version PostgreSQL
        $version = $this->connection->fetchOne('SELECT version()');
        
        // Query 2 - liste des tables
        $tables = $this->connection->fetchAllAssociative(
            "SELECT table_name FROM information_schema.tables 
             WHERE table_schema = 'public'"
        );
        
        // Query 3 - statistiques
        $stats = $this->connection->fetchAssociative(
            "SELECT 
                (SELECT count(*) FROM pg_stat_user_tables) as tables_count,
                pg_database_size(current_database()) as db_size"
        );

        return $this->json([
            'version' => $version,
            'tables_count' => count($tables),
            'stats' => $stats
        ]);
    }
}