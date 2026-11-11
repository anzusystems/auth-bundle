<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenToolResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PersonalAccessTokenToolsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PersonalAccessTokenToolResolver $toolResolver,
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

        $toolNames = $this->resolveToolNames($constraint);
        $hasUnknownTool = array_any(
            $value,
            static fn (mixed $toolName): bool => false === in_array(needle: $toolName, haystack: $toolNames, strict: true),
        );
        if ($hasUnknownTool) {
            $this->context->addViolation($constraint->message);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveToolNames(PersonalAccessTokenTools $constraint): array
    {
        if ($constraint->writeTools) {
            return $this->toolResolver->resolveWriteToolNames();
        }
        if ($constraint->explicitTools) {
            return $this->toolResolver->resolveExplicitReadToolNames();
        }

        return $this->toolResolver->resolveReadToolNames();
    }
}
