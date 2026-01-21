<?php

namespace App\Controller;

use App\Service\TelemetryService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DatabaseTestController extends AbstractController
{
    public function __construct(
        private TelemetryService $telemetry,
        private Connection $connection
    ) {}

    #[Route('/api/test-db', name: 'api_test_db', methods: ['GET'])]
    public function testDatabase(): Response
    {
        return $this->telemetry->trace('test-database-endpoint', function ($mainSpan) {
            
            // Query 1 avec span dédié
            $versionSpan = $this->telemetry->startSpan('db.query.version', [
                'db.system' => 'postgresql',
                'db.statement' => 'SELECT version()'
            ]);
            $version = $this->connection->fetchOne('SELECT version()');
            $versionSpan->end();
            
            // Query 2 avec span dédié
            $tablesSpan = $this->telemetry->startSpan('db.query.tables', [
                'db.system' => 'postgresql',
                'db.statement' => 'SELECT table_name FROM information_schema.tables...'
            ]);
            $tables = $this->connection->fetchAllAssociative(
                "SELECT table_name FROM information_schema.tables 
                 WHERE table_schema = 'public'"
            );
            $tablesSpan->setAttribute('db.rows_returned', count($tables));
            $tablesSpan->end();
            
            // Query 3 avec span dédié
            $statsSpan = $this->telemetry->startSpan('db.query.stats', [
                'db.system' => 'postgresql',
            ]);
            $stats = $this->connection->fetchAssociative(
                "SELECT 
                    (SELECT count(*) FROM pg_stat_user_tables) as tables_count,
                    pg_database_size(current_database()) as db_size"
            );
            $statsSpan->end();

            return $this->json([
                'version' => $version,
                'tables_count' => count($tables),
                'stats' => $stats
            ]);
        });
    }
}