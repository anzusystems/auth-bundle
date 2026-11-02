<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
final class PersonalAccessTokenWriteToolsWithinTools extends Constraint
{
    public string $message = ValidationException::ERROR_FIELD_INVALID;

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
