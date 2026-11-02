<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security;

use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScopeProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class PersonalAccessTokenToolScopeProvider implements McpToolScopeProviderInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function provideAllowedToolNames(): ?array
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || false === $token->hasAttribute(PersonalAccessTokenAuthenticator::TOOLS_ATTRIBUTE)) {
            return null;
        }
        $tools = $token->getAttribute(PersonalAccessTokenAuthenticator::TOOLS_ATTRIBUTE);
        if (false === is_array($tools)) {
            return null;
        }

        return array_values(array_filter($tools, is_string(...)));
    }
}
