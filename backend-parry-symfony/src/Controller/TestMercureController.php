<?php

namespace App\Controller;

use App\Service\MercurePublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestMercureController extends AbstractController
{
    #[Route('/test-mercure', name: 'app_test_mercure')]
    public function index(MercurePublisher $publisher): Response
    {
        // On simule l'envoi d'une notif sur le topic "mon/chat"
        $publisher->publish('http://monsite.com/chat', [
            'status' => 'success',
            'message' => 'Hello depuis Symfony !',
            'time' => date('H:i:s')
        ]);

        return new Response('Événement publié ! Va voir le hub.');
    }
}