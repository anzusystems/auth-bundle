<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Controller\Api;

use AnzuSystems\AuthBundle\Domain\Process\RefreshTokenProcess;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;
use OpenApi\Attributes as OA;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[OA\Tag('Authorization')]
abstract class AbstractAuthController extends AbstractAnzuApiController
{
    public function __construct(
        private readonly RefreshTokenProcess $refreshTokenProcess,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws MissingConfigurationException
     * @throws InvalidJwtException
     */
    #[Route('refresh-token', name: 'refresh_token', methods: [Request::METHOD_POST, Request::METHOD_GET])]
    public function refreshToken(Request $request): JsonResponse
    {
        return $this->refreshTokenProcess->execute($request);
    }


    #[Route('logout', name: 'logout', methods: [Request::METHOD_GET])]
    #[OA\Response(
        response: Response::HTTP_FOUND,
        description: 'Redirect response back to configured url.'
    )]
    public function logout(Request $request): Response
    {
        /** @var LogoutEvent $event */
        $event = $this->eventDispatcher->dispatch(new LogoutEvent($request, null));

        return $event->getResponse() ?: new JsonResponse([
            'message' => 'Unhandled logout event.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
