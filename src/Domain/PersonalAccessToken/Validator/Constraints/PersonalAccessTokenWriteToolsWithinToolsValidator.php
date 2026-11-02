<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\AuthBundle\Entity\AbstractPersonalAccessToken;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class PersonalAccessTokenWriteToolsWithinToolsValidator extends ConstraintValidator
{
    private const string WRITE_TOOLS_PATH = 'writeTools';

    /**
     * @param PersonalAccessTokenWriteToolsWithinTools $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === $value instanceof AbstractPersonalAccessToken) {
            throw new UnexpectedValueException($value, AbstractPersonalAccessToken::class);
        }
        $tools = $value->getTools();
        if (null === $tools) {
            return;
        }
        if (empty(array_diff($value->getWriteTools(), $tools))) {
            return;
        }
        $this->context
            ->buildViolation($constraint->message)
            ->atPath(self::WRITE_TOOLS_PATH)
            ->addViolation();
    }
}
