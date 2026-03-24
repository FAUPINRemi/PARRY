<?php

namespace App\Security;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class RateLimiter
{
    private const MAX_REQUESTS_PER_SECOND = 15;
    private const MAX_REQUESTS_PER_MINUTE = 300;
    private const MAX_REQUESTS_PER_HOUR = 5000;
    private const BLOCK_DURATION = 300; // 5 minutes en secondes
    
    private FilesystemAdapter $cache;
    
    public function __construct()
    {
        $this->cache = new FilesystemAdapter('rate_limiter', 3600);
    }
    
    /**
     * Vérifie si la requête est autorisée
     * @param string $identifier IP ou user ID
     * @param string $endpoint Route appelée (optionnel, pour limite spécifique)
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    private function sanitizeKey(string $identifier): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $identifier), 0, 64);
    }

    public function isAllowed(string $identifier, string $endpoint = 'global'): array
    {
        $identifier = $this->sanitizeKey($identifier);

        // Vérifier si l'IP est bloquée
        if ($this->isBlocked($identifier)) {
            return [
                'allowed' => false,
                'reason' => 'IP bloquée temporairement pour abus. Réessayez dans 5 minutes.',
                'retry_after' => $this->getBlockTimeRemaining($identifier)
            ];
        }
        
        // Vérifier limite par seconde
        if (!$this->checkLimit($identifier, 'second', self::MAX_REQUESTS_PER_SECOND, 1)) {
            $this->incrementAbuse($identifier);
            return [
                'allowed' => false,
                'reason' => 'Trop de requêtes par seconde. Maximum 2/s.',
                'retry_after' => 1
            ];
        }
        
        // Vérifier limite par minute
        if (!$this->checkLimit($identifier, 'minute', self::MAX_REQUESTS_PER_MINUTE, 60)) {
            $this->incrementAbuse($identifier);
            return [
                'allowed' => false,
                'reason' => 'Trop de requêtes par minute. Maximum 60/min.',
                'retry_after' => 60
            ];
        }
        
        // Vérifier limite par heure
        if (!$this->checkLimit($identifier, 'hour', self::MAX_REQUESTS_PER_HOUR, 3600)) {
            $this->incrementAbuse($identifier);
            return [
                'allowed' => false,
                'reason' => 'Trop de requêtes par heure. Maximum 500/h.',
                'retry_after' => 3600
            ];
        }
        
        // Enregistrer la requête
        $this->recordRequest($identifier, 'second', 1);
        $this->recordRequest($identifier, 'minute', 60);
        $this->recordRequest($identifier, 'hour', 3600);
        
        return ['allowed' => true, 'reason' => null];
    }
    
    private function checkLimit(string $identifier, string $window, int $maxRequests, int $ttl): bool
    {
        $key = "rate_limit_{$identifier}_{$window}";
        
        $count = $this->cache->get($key, function (ItemInterface $item) use ($ttl) {
            $item->expiresAfter($ttl);
            return 0;
        });
        
        return $count < $maxRequests;
    }
    
    private function recordRequest(string $identifier, string $window, int $ttl): void
    {
        $key = "rate_limit_{$identifier}_{$window}";

        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            // Nouvelle fenêtre : initialiser avec TTL fixe
            $item->set(1);
            $item->expiresAfter($ttl);
        } else {
            // Fenêtre existante : incrémenter SANS réinitialiser le TTL
            $item->set((int)$item->get() + 1);
        }
        $this->cache->save($item);
    }
    
    private function incrementAbuse(string $identifier): void
    {
        $key = "abuse_count_{$identifier}";
        
        $abuseCount = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(3600); // 1 heure
            return 0;
        });
        
        $abuseCount++;
        
        // Si > 10 abus en 1h, bloquer l'IP
        if ($abuseCount >= 10) {
            $this->blockIdentifier($identifier);
        }
        
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($abuseCount) {
            $item->expiresAfter(3600);
            return $abuseCount;
        });
    }
    
    private function blockIdentifier(string $identifier): void
    {
        $key = "blocked_{$identifier}";
        $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(self::BLOCK_DURATION);
            return time();
        });
    }
    
    private function isBlocked(string $identifier): bool
    {
        $key = "blocked_{$identifier}";
        return $this->cache->hasItem($key);
    }
    
    private function getBlockTimeRemaining(string $identifier): int
    {
        $key = "blocked_{$identifier}";
        $item = $this->cache->getItem($key);
        
        if (!$item->isHit()) {
            return 0;
        }
        
        return self::BLOCK_DURATION;
    }
    
    public function unblock(string $identifier): void
    {
        $identifier = $this->sanitizeKey($identifier);
        $this->cache->delete("blocked_{$identifier}");
        $this->cache->delete("abuse_count_{$identifier}");
    }
}