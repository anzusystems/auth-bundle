<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process;

use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Exception\InvalidJwtException;
use AnzuSystems\AuthBundle\Exception\InvalidRefreshTokenException;
use AnzuSystems\AuthBundle\Exception\MissingConfigurationException;
use AnzuSystems\AuthBundle\Util\HttpUtil;
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
     * @throws MissingConfigurationException
     * @throws InvalidJwtException
     */
    public function execute(Request $request, JsonResponse $response = null): JsonResponse
    {
        $response ??= new JsonResponse();
        try {
            [$userId, $tokenHash] = $this->httpUtil->grabRefreshTokenFromRequest($request);
        } catch (InvalidRefreshTokenException) {
            return $response
                ->setStatusCode(Response::HTTP_UNAUTHORIZED)
                ->setData(['message' => 'unauthorized'])
            ;
        }
        $deviceId = $this->httpUtil->grabDeviceIdFromRequest($request);

        if ($this->refreshTokenStorage->isValid($userId, $deviceId ?: '', $tokenHash)) {
            return $this->grantAccessOnResponseProcess->execute($userId, $request, $response);
        }

        return $response
            ->setStatusCode(Response::HTTP_BAD_REQUEST)
            ->setData(['message' => 'unable_to_refresh'])
        ;
    }
}
