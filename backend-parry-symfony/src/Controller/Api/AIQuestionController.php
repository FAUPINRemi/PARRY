<?php

namespace App\Controller\Api;

use App\Service\AI\QuestionGeneratorAI;
use App\Service\AI\ResponseGeneratorAI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ai')]
class AIQuestionController extends AbstractController
{
    #[Route('/question', name: 'api_ai_generate_question', methods: ['POST'])]
    public function generateQuestion(Request $request, QuestionGeneratorAI $questionGeneratorAI): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $context = $data['context'] ?? [];
        try {
            $question = $questionGeneratorAI->generateQuestion(is_array($context) ? $context : []);
            return $this->json(['question' => $question]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/answer', name: 'api_ai_generate_answer', methods: ['POST'])]
    public function generateAnswer(Request $request, ResponseGeneratorAI $responseGeneratorAI): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';
        $context = $data['context'] ?? [];
        try {
            $answer = $responseGeneratorAI->generateResponse($question, is_array($context) ? $context : []);
            return $this->json(['answer' => $answer]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
