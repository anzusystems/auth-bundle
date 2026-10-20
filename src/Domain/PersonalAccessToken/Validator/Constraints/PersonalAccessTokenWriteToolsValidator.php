<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver\PersonalAccessTokenWriteToolResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PersonalAccessTokenWriteToolsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PersonalAccessTokenWriteToolResolver $writeToolResolver,
    ) {
    }

    /**
     * @param PersonalAccessTokenWriteTools $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === is_array($value)) {
            return;
        }
        $writeTools = $this->writeToolResolver->resolveWriteTools();
        foreach ($value as $toolName) {
            if (in_array(needle: $toolName, haystack: $writeTools, strict: true)) {
                continue;
            }
            $this->context->addViolation($constraint->message);

            return;
        }
    }
}
