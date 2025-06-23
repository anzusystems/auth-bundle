<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process;

use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Exception\InvalidRefreshTokenException;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RefreshTokenProcess
{
    public function __construct(
        private readonly GrantAccessOnResponseProcess $grantAccessOnResponseProcess,
        private readonly HttpUtil $httpUtil,
        private readonly RefreshTokenStorageInterface $refreshTokenStorage,
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(Request $request, ?Response $response = null): Response
    {
        $response ??= new JsonResponse();
        try {
            [$userId, $tokenHash] = $this->httpUtil->grabRefreshTokenFromRequest($request);
        } catch (InvalidRefreshTokenException) {
            if ($response instanceof JsonResponse) {
                $response->setData(['message' => 'unauthorized']);
            }

            return $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
        }
        $deviceId = $this->httpUtil->grabDeviceIdFromRequest($request);

        if ($this->refreshTokenStorage->isValid($userId, $deviceId ?: '', $tokenHash)) {
            return $this->grantAccessOnResponseProcess->execute($userId, $request, $response);
        }

        if ($response instanceof JsonResponse) {
            $response->setData(['message' => 'unable_to_refresh']);
        }

        return $response->setStatusCode(Response::HTTP_BAD_REQUEST);
    }
}
