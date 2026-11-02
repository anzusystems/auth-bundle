<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PersonalAccessTokenToolsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PersonalAccessTokenToolResolver $toolResolver,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param PersonalAccessTokenTools $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === is_array($value)) {
            return;
        }
        $allowedToolNames = $this->resolveAllowedToolNames();
        $hasNotAllowedTool = array_any(
            $value,
            static fn (mixed $toolName): bool => false === in_array(needle: $toolName, haystack: $allowedToolNames, strict: true),
        );
        if ($hasNotAllowedTool) {
            $this->context->addViolation($constraint->message);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveAllowedToolNames(): array
    {
        if (null === $this->tokenStorage->getToken()?->getUser()) {
            return $this->toolResolver->resolveToolNames();
        }

        return $this->toolResolver->resolveGrantableToolNames();
    }
}
