<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\Process;

use AnzuSystems\AuthBundle\Configuration\CookieConfiguration;
use AnzuSystems\AuthBundle\Configuration\JwtConfiguration;
use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Model\DeviceDto;
use AnzuSystems\AuthBundle\Model\RefreshTokenDto;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use AnzuSystems\AuthBundle\Util\JwtUtil;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GrantAccessOnResponseProcess
{
    public function __construct(
        private readonly CookieConfiguration $cookieConfiguration,
        private readonly JwtConfiguration $jwtConfiguration,
        private readonly JwtUtil $jwtUtil,
        private readonly HttpUtil $httpUtil,
        private readonly RefreshTokenStorageInterface $refreshTokenStorage,
    ) {
    }

    /**
     * @throws Exception
     */
    public function execute(string $userId, Request $request, Response $response = null): Response
    {
        $jwtExpiresAt = new DateTimeImmutable(sprintf('+%d seconds', $this->jwtConfiguration->getLifetime()));
        $jwt = $this->jwtUtil->create($userId, $jwtExpiresAt);
        $deviceId = $this->httpUtil->grabDeviceIdFromRequest($request) ?: uuid_create();
        $refreshTokenDto = RefreshTokenDto::create(
            userId: $userId,
            device: DeviceDto::createFromRequest($deviceId, $request),
            expiresAt: new DateTimeImmutable(sprintf('+%d seconds', $this->cookieConfiguration->getRefreshTokenLifetime())),
        );

        $response ??= new JsonResponse();
        $this->httpUtil->storeJwtOnResponse($response, $jwt);
        $this->httpUtil->storeRefreshTokenOnResponse($response, $refreshTokenDto);
        $this->httpUtil->storeDeviceIdOnResponse($response, $deviceId);
        $this->refreshTokenStorage->store($refreshTokenDto);

        if ($response instanceof JsonResponse) {
            $response->setData([
                'access_token' => $jwt->toString(),
                'refresh_token' => $refreshTokenDto->toString(),
            ]);
        }

        return $response;
    }
}
