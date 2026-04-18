<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VertexAiImageClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectId,
        private readonly string $location,
        private readonly string $imageModel,   
        private readonly string $accessToken,  
    ) {}

    /**
     * @param array{prompt:string,width:int,height:int,mime:string,numberOfImages:int} $args
     * @return array{base64:string,mime:string}
     */
    public function generateImageBase64(array $args): array
    {
        $this->logger->warning('[VERTEX_IMAGE_OUT] Requête génération image envoyée à Vertex AI.');

     
        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:predict',
            $this->location,
            $this->projectId,
            $this->location,
            $this->imageModel
        );

      
        $payload = [
            'instances' => [
                [
                    'prompt' => $args['prompt'],
                ],
            ],
            'parameters' => [
                'sampleCount' => $args['numberOfImages'],
            ],
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 60,
        ]);

        $data = $response->toArray(false);

        $base64 =
            $data['predictions'][0]['bytesBase64Encoded']
            ?? $data['predictions'][0]['image']['bytesBase64Encoded']
            ?? null;

        if (!$base64 || !is_string($base64)) {
            $this->logger->error('[VERTEX_IMAGE] Réponse inattendue', [
                'status' => $response->getStatusCode(),
                'body' => $data,
            ]);
            throw new \RuntimeException('VERTEX_IMAGE_REPONSE_INATTENDUE');
        }

        return [
            'base64' => $base64,
            'mime' => $args['mime'] ?? 'image/png',
        ];
    }
}