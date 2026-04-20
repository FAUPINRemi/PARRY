<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\Round;
use App\Entity\User;
use App\Repository\RoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use \App\Service\GameRedisService;
use App\Service\MercurePublisherService;

class RoundService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoundRepository $roundRepository,
        private readonly GameRedisService $gameRedisService,
        private readonly MercurePublisherService $mercurePublisher
    ) {}

    public function createRound(Game $game, ?User $questionMaster = null): Round {

        if ($game->getStatus() !== \App\Enum\GameStatus::IN_PROGRESS) {
            throw new \RuntimeException('PARTIE_NON_COMMENCEE', 400);
        }

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $playersRaw = $this->gameRedisService->getRedis()->hgetall("game:{$gameIdentifier}:players") ?: [];

        $aliveIds = [];
        foreach ($playersRaw as $playerId => $playerJson) {
            $p = json_decode($playerJson, true);
            if ($p['isAlive'] ?? true) {
                $aliveIds[] = $playerId;
            }
        }

        if (!empty($aliveIds)) {
            $randomId = $aliveIds[array_rand($aliveIds)];
            $picked = $game->getPlayers()->filter(
                fn(User $u) => $u->getId()->toString() === $randomId
            )->first();
            if ($picked) {
                $questionMaster = $picked;
            }
        }

        if (!$questionMaster) {
            throw new \RuntimeException('AUCUN_JOUEUR_VIVANT', 400);
        }

        $round = new Round();
        $round->setGame($game);
        $round->setRoundNumber($game->getRounds()->count() + 1);
        $round->setQuestionMaster($questionMaster);
        $round->setQuestionMasterId($questionMaster->getId());

        $this->entityManager->persist($round);
        $this->entityManager->flush();

        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'roundId', $round->getId()->toString());
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'roundNumber', $round->getRoundNumber());
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'questionAskedBy', $questionMaster->getId()->toString());
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'status', 'en_attente_question');

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'round_created']);

        return $round;
    }

    public function questionRound(Round $round, User $user, string $questionTexte): void {

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();
        $statusRound = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:round", 'status');

        if ($statusRound !== 'en_attente_question') {
            throw new \RuntimeException('QUESTION_DEJA_SOUMISE', 400);
        }

        if (!$game->getPlayers()->contains($user)) {
            throw new \RuntimeException('JOUEUR_PAS_DANS_PARTIE', 403);
        }

        $playerData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $user->getId()->toString());

        if ($playerData) {
            $data = json_decode($playerData, true);
            if (!$data['isAlive']) {
                throw new \RuntimeException('JOUEUR_ELIMINE', 403);
            }
        }

        $round->setQuestion($questionTexte);
        $round->setQuestionMaster($user);
        $round->setQuestionMasterId($user->getId());
        $this->entityManager->flush();

        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'question', $questionTexte);
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'questionAskedBy', $user->getId()->toString());
        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'status', 'en_attente_reponses');

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'state_changed']);
    }

    public function reponseRound(Round $round, User $user, string $responseTexte): void {

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $statusRound = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:round", 'status');

        if ($statusRound !== 'en_attente_reponses') {
            throw new \RuntimeException('REPONSE_NON_ATTENDUE', 400);
        }

        if (!$game->getPlayers()->contains($user)) {
            throw new \RuntimeException('JOUEUR_PAS_DANS_PARTIE', 403);
        }

        $playerData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $user->getId()->toString());

        if ($playerData) {
            $data = json_decode($playerData, true);
            if (!$data['isAlive']) {
                throw new \RuntimeException('JOUEUR_ELIMINE', 403);
            }
        }

        $dejarepondu = $this->gameRedisService->getRedis()->hexists("game:{$gameIdentifier}:round:reponses", $user->getId()->toString());

        if ($dejarepondu) {
            throw new \RuntimeException('REPONSE_DEJA_SOUMISE', 400);
        }

        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round:reponses", $user->getId()->toString(), json_encode(['reponse' => $responseTexte, 'timestamp' => time()]));

        $joueursOK = 0;
        foreach ($game->getPlayers() as $player) {
            $joueurData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $player->getId()->toString());
            if ($joueurData) {
                $data = json_decode($joueurData, true);
                if ($data['isAlive']) {
                    $joueursOK++;
                }
            }
        }

        $nbReponses = $this->gameRedisService->getRedis()->hlen("game:{$gameIdentifier}:round:reponses");

        if ($nbReponses >= $joueursOK) {
            $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'status', 'en_attente_votes');
        }

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'state_changed']);
    }

    public function voteRound(Round $round, User $uservote, string $IdJoueurVote): void {

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $revoteData = $this->gameRedisService->getRedis()->get("game:{$gameIdentifier}:round:revote");
        if ($revoteData) {
            $candidatsRevote = json_decode($revoteData, true);
            if (!in_array($IdJoueurVote, $candidatsRevote)) {
                throw new \RuntimeException('VOTE_HORS_REVOTE', 400);
            }
        }

        $statusRound = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:round", 'status');

        if ($statusRound !== 'en_attente_votes') {
            throw new \RuntimeException('ROUND_PAS_EN_ATTENTE_VOTES', 400);
        }

        if (!$game->getPlayers()->contains($uservote)) {
            throw new \RuntimeException('JOUEUR_PAS_DANS_PARTIE', 403);
        }

        $uservoteData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $uservote->getId()->toString());
        if ($uservoteData) {
            $data = json_decode($uservoteData, true);
            if (!$data['isAlive']) {
                throw new \RuntimeException('JOUEUR_VOTE_ELIMINE', 403);
            }
        }

        $cibleData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $IdJoueurVote);
        if (!$cibleData) {
            throw new \RuntimeException('JOUEUR_VOTE_INEXISTANT', 404);
        }

        $cibleDataArr = json_decode($cibleData, true);
        if (!$cibleDataArr['isAlive']) {
            throw new \RuntimeException('JOUEUR_VOTE_DEJA_ELIMINE', 400);
        }

        $dejaVote = $this->gameRedisService->getRedis()->hexists("game:{$gameIdentifier}:round:votes", $uservote->getId()->toString());
        if ($dejaVote) {
            throw new \RuntimeException('VOTE_DEJA_SOUMIS', 400);
        }

        $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round:votes", $uservote->getId()->toString(), json_encode(['votePour' => $IdJoueurVote, 'timestamp' => time()]));

        $joueursVivants = 0;

        foreach ($game->getPlayers() as $player) {
            $joueurData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $player->getId()->toString());
            if ($joueurData) {
                $data = json_decode($joueurData, true);
                if ($data['isAlive']) {
                    $joueursVivants++;
                }
            }
        }

        $nbVotes = $this->gameRedisService->getRedis()->hlen("game:{$gameIdentifier}:round:votes");

        if ($nbVotes >= $joueursVivants) {
            $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'status', 'termine');
        }

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'state_changed']);
    }

    public function eliminerJoueur(Round $round): ?string {

        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $statusRound = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:round", 'status');

        if ($statusRound !== 'termine') {
            throw new \RuntimeException('ROUND_NON_TERMINE', 400);
        }

        $votes = $this->gameRedisService->getRedis()->hgetall("game:{$gameIdentifier}:round:votes");
        if (empty($votes)) {
            throw new \RuntimeException('AUCUN_VOTE', 400);
        }

        $countVotes = [];
        foreach ($votes as $votantId => $voteData) {
            $voteArray = json_decode($voteData, true);
            $votePour = $voteArray['votePour'];

            if (!isset($countVotes[$votePour])) {
                $countVotes[$votePour] = 0;
            }
            $countVotes[$votePour]++;
        }

        arsort($countVotes);
        $joueurElimine = array_key_first($countVotes);
        $maxVotes = $countVotes[$joueurElimine];

        $joueurMaxVotes = array_keys(array_filter($countVotes, fn($votes) => $votes === $maxVotes));

        if (count($joueurMaxVotes) > 1) {
            $this->gameRedisService->getRedis()->setex("game:{$gameIdentifier}:round:revote", 3600, json_encode($joueurMaxVotes));
            $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:round", 'status', 'en_attente_votes');
            $this->gameRedisService->getRedis()->del("game:{$gameIdentifier}:round:votes");

            $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'state_changed']);
            return null;
        }

        $playerData = $this->gameRedisService->getRedis()->hget("game:{$gameIdentifier}:players", $joueurElimine);
        if ($playerData) {
            $data = json_decode($playerData, true);
            $data['isAlive'] = false;
            $this->gameRedisService->getRedis()->hset("game:{$gameIdentifier}:players", $joueurElimine, json_encode($data));
        }

        $userRepository = $this->entityManager->getRepository(User::class);
        $eliminatedUser = $userRepository->find($joueurElimine);
        if ($eliminatedUser) {
            $round->setEliminatedPlayer($eliminatedUser);
            $this->entityManager->flush();
        }

        $this->mercurePublisher->publish("/game/{$gameIdentifier}", ['event' => 'state_changed']);

        return $joueurElimine;
    }

    public function finRound(Round $round): void {
        $game = $round->getGame();
        $gameIdentifier = $game->getCode() ?? $game->getId()->toString();

        $this->gameRedisService->getRedis()->del("game:{$gameIdentifier}:round:reponses");
        $this->gameRedisService->getRedis()->del("game:{$gameIdentifier}:round:votes");
        $this->gameRedisService->getRedis()->del("game:{$gameIdentifier}:round:revote");
    }
}