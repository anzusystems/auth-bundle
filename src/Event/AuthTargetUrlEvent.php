<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Event;

use AnzuSystems\AuthBundle\Contracts\AnzuAuthUserInterface;
use AnzuSystems\AuthBundle\Model\Enum\UserOAuthLoginState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

final class AuthTargetUrlEvent extends Event
{
    public const string NAME = 'auth.targetUrl';

    public function __construct(
        private string $targetUrl,
        private readonly Request $request,
        private readonly UserOAuthLoginState $loginState,
        private readonly ?AnzuAuthUserInterface $authUser = null
    ) {
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $targetUrl): self
    {
        $this->targetUrl = $targetUrl;

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getLoginState(): UserOAuthLoginState
    {
        return $this->loginState;
    }

    public function getAuthUser(): ?AnzuAuthUserInterface
    {
        return $this->authUser;
    }
}
