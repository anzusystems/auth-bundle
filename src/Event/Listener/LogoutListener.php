<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Event\Listener;

use AnzuSystems\AuthBundle\Contracts\RefreshTokenStorageInterface;
use AnzuSystems\AuthBundle\Exception\InvalidRefreshTokenException;
use AnzuSystems\AuthBundle\Util\HttpUtil;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
final class LogoutListener
{
    public function __construct(
        private readonly HttpUtil $httpUtil,
        private readonly RefreshTokenStorageInterface $refreshTokenStorage,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        try {
            $userId = $this->httpUtil->grabRefreshTokenFromRequest($request)[0];
            $deviceId = $this->httpUtil->grabDeviceIdFromRequest($request);
            if ($deviceId) {
                $this->refreshTokenStorage->invalidate($userId, $deviceId);
            }
        } catch (InvalidRefreshTokenException) {
            // do nothing
        }

        $response = new RedirectResponse($this->httpUtil->getAuthRedirectUrlFromRequest($request));
        $this->httpUtil->destroyJwtOnResponse($response);
        $this->httpUtil->destroyRefreshTokenOnResponse($response);
        $event->setResponse($response);
    }
}
