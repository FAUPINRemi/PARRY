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
        '/api/doc',       
        '/api/doc.json',   
        '/_profiler',      
        '/api/ai/',        
    ];

    private const EXCLUDED_GET_SUFFIXES = [
        '/state',         
        '/my-role',        
    ];

    private const EXCLUDED_POST_SUFFIXES = [
        '/start',         
        '/create',         
        '/eliminate',     
        '/finish',         
        '/check-victory',  
        '/delete',         
        '/restart',       
        '/delete',         
        '/leave',          
    ];

    private const EXCLUDED_GET_EXACT = [
        '/api/game/active', 
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
            foreach (self::EXCLUDED_GET_EXACT as $exact) {
                if ($path === $exact) {
                    return;
                }
            }
        }

        // Exclure les actions d'orchestration POST (créateur uniquement)
        if ($request->getMethod() === 'POST') {
            foreach (self::EXCLUDED_POST_SUFFIXES as $suffix) {
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
        
        if (!str_starts_with($path, '/api')) {
            return;
        }
        
        $identifier = $this->getIdentifier($request);
        
        $result = $this->rateLimiter->isAllowed($identifier, $path);
        
        if (!$result['allowed']) {
            $this->logger->warning('Rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'path' => $path,
                'reason' => $result['reason'],
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            
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
   
        $token = null;

        $auth = $request->headers->get('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
        }

        if ($token === null) {
            $token = $request->cookies->get('access_token');
        }

        if ($token !== null) {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $pad = strlen($parts[1]) % 4;
                $padded = $pad === 0 ? $parts[1] : $parts[1] . str_repeat('=', 4 - $pad);
                $payload = json_decode(base64_decode(strtr($padded, '-_', '+/')), true);
                if (isset($payload['username'])) {
                    return 'user_' . $payload['username'];
                }
            }
        }

        $ip = $request->getClientIp();
        return 'ip_' . $ip;
    }
}