<?php

namespace App\EventSubscriber;

use App\Security\RateLimiter;
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

    // Routes GET exclues du rate limiting (polling légitime)
    private const EXCLUDED_GET_SUFFIXES = [
        '/state',          // Polling d'état de la partie (toutes les 2.5s)
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

        // Les OPTIONS (CORS preflight) ne comptent pas comme des vraies requêtes
        if ($request->getMethod() === 'OPTIONS') {
            return;
        }

        $path = $request->getPathInfo();

        // Exclure les routes GET de polling légitime
        if ($request->getMethod() === 'GET') {
            foreach (self::EXCLUDED_GET_SUFFIXES as $suffix) {
                if (str_ends_with($path, $suffix)) {
                    return;
                }
            }
        }
        
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
        // Use JWT user identifier if available, so players behind the same NAT IP
        // each get their own independent rate limit bucket.
        $auth = $request->headers->get('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4 === 0 ? strlen($parts[1]) : strlen($parts[1]) + 4 - strlen($parts[1]) % 4, '=')), true);
                if (isset($payload['username'])) {
                    return 'user_' . $payload['username'];
                }
            }
        }

        $ip = $request->getClientIp();
        return 'ip_' . $ip;
    }
}