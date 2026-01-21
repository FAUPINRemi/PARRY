<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenTelemetry\API\Globals;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        // Créer une trace manuelle
        $tracer = Globals::tracerProvider()->getTracer('test-controller');
        
        $span = $tracer->spanBuilder('test-request')
            ->startSpan();
        
        $span->setAttribute('test.attribute', 'test-value');
        $span->addEvent('Processing test request');
        
        // Simuler un traitement
        usleep(100000); // 100ms
        
        $span->end();
        
        return new Response('Test trace sent to Uptrace!');
    }
}