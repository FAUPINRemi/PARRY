<?php

namespace App\EventSubscriber;

use App\Service\Security\RateLimiter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RateLimitSubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_ROUTES = [
        '/api/doc',        // Swagger UI
        '/api/doc.json',   // Swagger JSON
        '/_profiler',      // Symfony Profiler
    ];
    
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }
    
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        
        // Ne pas appliquer le rate limit sur certaines routes
        foreach (self::EXCLUDED_ROUTES as $excludedRoute) {
            if (str_starts_with($path, $excludedRoute)) {
                return;
            }
        }
        
        // Appliquer seulement sur les routes /api
        if (!str_starts_with($path, '/api')) {
            return;
        }
        
        // Identifier l'utilisateur par IP
        $identifier = $this->getIdentifier($request);
        
        // Vérifier le rate limit
        $result = $this->rateLimiter->isAllowed($identifier, $path);
        
        if (!$result['allowed']) {
            // Logger la tentative d'abus
            $this->logger->warning('Rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'path' => $path,
                'reason' => $result['reason'],
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            
            // Répondre avec 429 Too Many Requests
            $response = new JsonResponse([
                'error' => 'Rate limit exceeded',
                'message' => $result['reason'],
                'retry_after' => $result['retry_after'] ?? 60
            ], 429);
            
            $response->headers->set('Retry-After', (string)($result['retry_after'] ?? 60));
            $response->headers->set('X-RateLimit-Limit', '2');
            $response->headers->set('X-RateLimit-Remaining', '0');
            
            $event->setResponse($response);
        }
    }
    
    private function getIdentifier($request): string
    {
        $ip = $request->getClientIp();
        return 'ip_' . $ip;
    }
}