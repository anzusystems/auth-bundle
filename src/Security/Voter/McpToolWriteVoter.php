<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Voter;

use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @template-extends Voter<string, string>
 */
final class McpToolWriteVoter extends Voter
{
    public function supportsAttribute(string $attribute): bool
    {
        return McpToolAccessChecker::WRITE_PERMISSION === $attribute;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return McpToolAccessChecker::WRITE_PERMISSION === $attribute && is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (false === $token->hasAttribute(PersonalAccessTokenAuthenticator::WRITE_TOOLS_ATTRIBUTE)) {
            return false;
        }
        $writeTools = $token->getAttribute(PersonalAccessTokenAuthenticator::WRITE_TOOLS_ATTRIBUTE);

        return is_array($writeTools) && in_array(needle: $subject, haystack: $writeTools, strict: true);
    }
}
