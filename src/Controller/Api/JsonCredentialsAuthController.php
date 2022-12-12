<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(name: 'auth_')]
#[OA\Tag('Authorization')]
final class JsonCredentialsAuthController extends AbstractAuthController
{
    #[Route('login', name: 'login', methods: [Request::METHOD_POST])]
    #[OA\RequestBody(
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'username'),
            new OA\Property(property: 'password'),
        ]),
    )]
    #[OA\Response(
        response: Response::HTTP_NO_CONTENT,
        description: 'Set cookies on response and return token response.',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'access_token'),
            new OA\Property(property: 'refresh_token'),
        ])
    )]
    public function login(): JsonResponse
    {
        return $this->noContentResponse();
    }
}
