<?php

namespace App\Controller\Api;

use App\Service\AI\AIOrchestrator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/game', name: 'api_game_')]
class GameController extends AbstractController
{
    public function __construct(
        private readonly AIOrchestrator $aiOrchestrator
    ) {}
    
    /**
     * Générer une question pour la manche
     */
    #[Route('/round/question', name: 'generate_question', methods: ['POST'])]
    public function generateQuestion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $question = $this->aiOrchestrator->generateQuestion([
                'round_number' => $data['round_number'] ?? 1,
                'previous_questions' => $data['previous_questions'] ?? []
            ]);
            
            return $this->json(['question' => $question]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * L'IA répond à une question
     */
    #[Route('/round/ai-response', name: 'ai_response', methods: ['POST'])]
    public function generateAIResponse(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';
        
        if (empty($question)) {
            return $this->json(['error' => 'Question manquante'], 400);
        }
        
        try {
            $response = $this->aiOrchestrator->generateResponse($question);
            
            return $this->json(['response' => $response]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Modérer une réponse utilisateur
     */
    #[Route('/moderate', name: 'moderate_content', methods: ['POST'])]
    public function moderateContent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';
        $type = $data['type'] ?? 'response';
        
        try {
            $result = $this->aiOrchestrator->moderateUserContent($content, $type);
            
            return $this->json($result);
            
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * L'IA vote (analyse toutes les réponses)
     */
    #[Route('/round/ai-vote', name: 'ai_vote', methods: ['POST'])]
    public function generateAIVote(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';
        $players = $data['players'] ?? [];
        
        if (empty($question) || empty($players)) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }
        
        try {
            $voteResult = $this->aiOrchestrator->analyzeAndVote($players, $question);
            
            return $this->json($voteResult);
            
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}