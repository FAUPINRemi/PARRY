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
    
    // gestion partie
    
    public function createGame(string $gameCode, array $gameData): void
    {
        $this->redis->hmset("game:{$gameCode}", [
            'status' => 'waiting',
            'currentRound' => 0,
            'maxRounds' => $gameData['maxRounds'] ?? 5,
            'createdAt' => time()
        ]);
        
        $this->redis->expire("game:{$gameCode}", 86400);
    }
    
    public function addPlayer(string $gameCode, int $playerId, string $nickname, bool $isAI = false): void
    {
        $this->redis->hset("game:{$gameCode}:players", $playerId, json_encode([
            'nickname' => $nickname,
            'isAI' => $isAI,
            'isAlive' => true
        ]));
    }
    
    public function getGame(string $gameCode): ?array
    {
        $game = $this->redis->hgetall("game:{$gameCode}");
        return $game ? $game : null;
    }
    
    public function getPlayers(string $gameCode): array
    {
        $players = $this->redis->hgetall("game:{$gameCode}:players");
        $result = [];
        foreach ($players as $playerId => $data) {
            $result[$playerId] = json_decode($data, true);
        }
        return $result;
    }
    
    public function startGame(string $gameCode): void
    {
        $this->redis->hset("game:{$gameCode}", 'status', 'playing');
    }
    
    // gestion round
    
    public function startRound(string $gameCode, int $roundNumber, string $question): void
    {
        $roundKey = "game:{$gameCode}:round:{$roundNumber}";
        
        $this->redis->hmset($roundKey, [
            'roundNumber' => $roundNumber,
            'question' => $question,
            'status' => 'waiting_responses',
            'startedAt' => time()
        ]);
        
        $this->redis->hset("game:{$gameCode}", 'currentRound', $roundNumber);
        $this->redis->expire($roundKey, 3600); // 1h
    }
    
    public function getRound(string $gameCode, int $roundNumber): ?array
    {
        $round = $this->redis->hgetall("game:{$gameCode}:round:{$roundNumber}");
        return $round ?: null;
    }
    
    // gestion réponses
    
    public function saveResponse(string $gameCode, int $roundNumber, int $playerId, string $content): void
    {
        $this->redis->hset(
            "game:{$gameCode}:round:{$roundNumber}:responses",
            $playerId,
            json_encode([
                'content' => $content,
                'submittedAt' => time()
            ])
        );
    }
    
    public function getResponses(string $gameCode, int $roundNumber): array
    {
        $responses = $this->redis->hgetall("game:{$gameCode}:round:{$roundNumber}:responses");
        $result = [];
        foreach ($responses as $playerId => $data) {
            $result[$playerId] = json_decode($data, true);
        }
        return $result;
    }
    
    public function allPlayersResponded(string $gameCode, int $roundNumber): bool
    {
        $alivePlayers = $this->getAlivePlayers($gameCode);
        $responses = $this->getResponses($gameCode, $roundNumber);
        
        return count($responses) >= count($alivePlayers);
    }
    
    // gestion votes
    public function saveVote(string $gameCode, int $roundNumber, int $voterId, int $votedForId): void
    {
        $this->redis->hset(
            "game:{$gameCode}:round:{$roundNumber}:votes",
            $voterId,
            $votedForId
        );
    }
    
    public function getVotes(string $gameCode, int $roundNumber): array
    {
        return $this->redis->hgetall("game:{$gameCode}:round:{$roundNumber}:votes");
    }
    
    public function allPlayersVoted(string $gameCode, int $roundNumber): bool
    {
        $alivePlayers = $this->getAlivePlayers($gameCode);
        $votes = $this->getVotes($gameCode, $roundNumber);
        
        return count($votes) >= count($alivePlayers);
    }
    
    // gestion elimination
    
    public function eliminatePlayer(string $gameCode, int $playerId): void
    {
        $this->redis->sadd("game:{$gameCode}:eliminated", $playerId);
        
        $playerData = json_decode($this->redis->hget("game:{$gameCode}:players", $playerId), true);
        $playerData['isAlive'] = false;
        $this->redis->hset("game:{$gameCode}:players", $playerId, json_encode($playerData));
    }
    
    public function isEliminated(string $gameCode, int $playerId): bool
    {
        return (bool) $this->redis->sismember("game:{$gameCode}:eliminated", $playerId);
    }
    
    public function getAlivePlayers(string $gameCode): array
    {
        $allPlayers = $this->getPlayers($gameCode);
        return array_filter($allPlayers, fn($player) => $player['isAlive']);
    }
    
    public function getAlivePlayersCount(string $gameCode): int
    {
        return count($this->getAlivePlayers($gameCode));
    }
    
    // clear
    
    public function deleteGame(string $gameCode): void
    {
        $keys = $this->redis->keys("game:{$gameCode}*");
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}