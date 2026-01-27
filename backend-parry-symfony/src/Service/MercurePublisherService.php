<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisherService
{
    public function __construct(
        private HubInterface $hub
    ) {}

    public function publish(string $topic, array $data): string
    {
        // On crée l'update. 
        // Le topic est l'URL ou l'identifiant de la ressource.
        // Les data sont encodées en JSON.
        $update = new Update(
            $topic,
            json_encode($data)
        );

        // On publie l'update sur le Hub
        return $this->hub->publish($update);
    }
}