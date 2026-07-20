<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class VertexAiImageClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectId,
        private readonly string $location,
        private readonly string $imageModel,   
        private readonly string $accessToken,  
    ) {}

    /**
     * @param array{prompt:string,width:int,height:int,mime:string,numberOfImages:int,quality?:int} $args
     * @return array{base64:string,mime:string}
     */
    // Génère une image via Vertex AI Imagen et renvoie le base64
    public function generateImageBase64(array $args): array
    {
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
                'width' => $args['width'],
                'height' => $args['height'],
                'mimeType' => $args['mime'],
                'compressionQuality' => $args['quality'] ?? 30,
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
            throw new \RuntimeException('VERTEX_IMAGE_REPONSE_INATTENDUE');
        }

        return [
            'base64' => $base64,
            'mime' => $args['mime'] ?? 'image/png',
        ];
    }
}