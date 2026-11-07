<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security;

use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScopeProviderInterface;
use LogicException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class PersonalAccessTokenToolScopeProvider implements McpToolScopeProviderInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function provideToolScope(): McpToolScope
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || false === $token->hasAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE)) {
            return new McpToolScope();
        }

        $toolScope = $token->getAttribute(PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE);
        if ($toolScope instanceof McpToolScope) {
            return $toolScope;
        }

        throw new LogicException(sprintf(
            'The token attribute "%s" must be a %s, "%s" given.',
            PersonalAccessTokenAuthenticator::TOOL_SCOPE_ATTRIBUTE,
            McpToolScope::class,
            get_debug_type($toolScope),
        ));
    }
}
