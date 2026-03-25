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
        '/api/ai/',        // Orchestration IA (créateur uniquement)
    ];

    // Routes GET exclues du rate limiting (polling légitime)
    private const EXCLUDED_GET_SUFFIXES = [
        '/state',          // Polling d'état de la partie (toutes les 2.5s)
        '/my-role',        // Récupération du rôle au démarrage de partie
    ];

    // Suffixes POST exclus : orchestration partie (créateur uniquement, non-spammable)
    private const EXCLUDED_POST_SUFFIXES = [
        '/start',          // Démarrage de partie
        '/create',         // Création de round
        '/eliminate',      // Élimination d'un joueur
        '/finish',         // Fin de round
        '/check-victory',  // Vérification de victoire
        '/restart',        // Relance de partie
        '/delete',         // Suppression de partie
        '/leave',          // Départ d'un joueur
    ];

    // Suffixes GET exclus supplémentaires
    private const EXCLUDED_GET_EXACT = [
        '/api/game/active', // Vérification de partie active (reconnexion)
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
        // Prefer per-user bucketing via JWT (cookie or Authorization header),
        // so players behind the same NAT IP each get their own rate limit bucket.
        $token = null;

        // 1) Authorization: Bearer header
        $auth = $request->headers->get('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
        }

        // 2) access_token cookie (httpOnly JWT set by JwtCookieAuthenticationSuccessHandler)
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