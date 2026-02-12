<?php

namespace App\Service;

use Predis\Client;

class GameRedisService
{
    private Client $redis;
    
    public function __construct(string $redisUrl)
    {
        $this->redis = new Client($redisUrl);
    }
    
    public function getRedis(): Client
    {
        return $this->redis;
    }
    
    public function createGame(string $gameCode, array $gameData): void
    {
        $this->redis->hset("game:{$gameCode}", 'status', 'waiting');
        $this->redis->hset("game:{$gameCode}", 'currentRound', 0);
        $this->redis->hset("game:{$gameCode}", 'maxRounds', $gameData['maxRounds'] ?? 5);
        $this->redis->hset("game:{$gameCode}", 'createdAt', time());
        
        $this->redis->expire("game:{$gameCode}", 86400);
    }
    
    public function addPlayer(string $gameCode, string $playerId, string $nickname, bool $isAI = false): void
    {
        $this->redis->hset(
            "game:{$gameCode}:players",
            $playerId,
            json_encode([
                'nickname' => $nickname,
                'isAlive' => true,
                'isAI' => $isAI
            ])
        );
    }
    
    public function startGame(string $gameCode): void
    {
        $this->redis->hset("game:{$gameCode}", 'status', 'playing');
    }
}