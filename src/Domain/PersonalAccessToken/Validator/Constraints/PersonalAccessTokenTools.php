<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class PersonalAccessTokenTools extends Constraint
{
    public string $message = ValidationException::ERROR_FIELD_INVALID;

    #[HasNamedArguments]
    public function __construct(
        public readonly bool $writeTools = false,
        public readonly bool $explicitTools = false,
    ) {
        parent::__construct();
    }
}
