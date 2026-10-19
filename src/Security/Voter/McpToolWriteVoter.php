<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Security\Voter;

use AnzuSystems\AuthBundle\Model\Enum\PersonalAccessTokenScope;
use AnzuSystems\AuthBundle\Security\Authentication\PersonalAccessTokenAuthenticator;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @template-extends Voter<string, mixed>
 */
final class McpToolWriteVoter extends Voter
{
    public function supportsAttribute(string $attribute): bool
    {
        return McpToolAccessChecker::WRITE_PERMISSION === $attribute;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return McpToolAccessChecker::WRITE_PERMISSION === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (false === $token->hasAttribute(PersonalAccessTokenAuthenticator::SCOPE_ATTRIBUTE)) {
            return false;
        }
        $scope = $token->getAttribute(PersonalAccessTokenAuthenticator::SCOPE_ATTRIBUTE);

        return $scope instanceof PersonalAccessTokenScope && $scope->is(PersonalAccessTokenScope::ReadWrite);
    }
}
