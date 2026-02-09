<?php

namespace App\Controller\Api;

use App\Service\AI\AIOrchestrator;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/game/round/question',
        summary: 'Générer une question pour la manche',
        tags: ['AI']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'round_number', type: 'integer', example: 1),
                new OA\Property(property: 'previous_questions', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Question générée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'question', type: 'string', example: 'Quel est ton plat préféré ?')
            ]
        )
    )]
    #[OA\Response(response: 500, description: 'Erreur de génération')]
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
    #[OA\Post(
        path: '/api/game/round/ai-response',
        summary: 'L\'IA génère une réponse à une question',
        tags: ['AI']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['question'],
            properties: [
                new OA\Property(property: 'question', type: 'string', example: 'Quel est ton film préféré ?')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Réponse générée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'response', type: 'string', example: 'J\'adore Inception, c\'est vraiment bien')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Question manquante')]
    #[OA\Response(response: 500, description: 'Erreur serveur')]
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
    #[OA\Post(
        path: '/api/game/moderate',
        summary: 'Modérer un contenu',
        tags: ['AI']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['content'],
            properties: [
                new OA\Property(property: 'content', type: 'string', example: 'Mon contenu à modérer'),
                new OA\Property(property: 'type', type: 'string', enum: ['question', 'response'], example: 'response')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Résultat de la modération',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'is_safe', type: 'boolean', example: true),
                new OA\Property(property: 'flagged_reasons', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'severity', type: 'string', enum: ['none', 'low', 'medium', 'high'])
            ]
        )
    )]
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
    #[OA\Post(
        path: '/api/game/round/ai-vote',
        summary: 'L\'IA vote pour un joueur suspect',
        tags: ['AI']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['question', 'players'],
            properties: [
                new OA\Property(property: 'question', type: 'string', example: 'Quel est ton film préféré ?'),
                new OA\Property(
                    property: 'players',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'player_id', type: 'string', example: 'player_abc123'),
                            new OA\Property(property: 'response', type: 'string', example: 'J\'aime les films d\'action'),
                            new OA\Property(property: 'is_ai', type: 'boolean', example: false)
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Vote généré',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'voted_for', type: 'string', example: 'player_abc123')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    public function generateAIVote(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';
        $players = $data['players'] ?? [];
        
        if (empty($question) || empty($players)) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }
        
        try {
            $voteResult = $this->aiOrchestrator->voteAsHuman($players, $question);
            
            return $this->json(['voted_for' => $voteResult]);
              
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}