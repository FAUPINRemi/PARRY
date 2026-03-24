<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JwtCookieAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!is_object($user)) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur invalide'], 500);
        }

        $jwt = $this->jwtManager->create($user);

        $isHttps = $request->isSecure(); 

        $cookie = Cookie::create('access_token')
            ->withValue($jwt)
            ->withHttpOnly(true)
            ->withSecure($isHttps)
            ->withSameSite($isHttps ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX)
            ->withPath('/');

        $response = new JsonResponse([
            'success' => true,
            'user' => [
                'id' => method_exists($user, 'getId') ? $user->getId() : null,
                'username' => method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null,
            ],
        ]);

        $response->headers->setCookie($cookie);

        return $response;
    }
}