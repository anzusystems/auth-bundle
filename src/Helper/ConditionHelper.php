<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Helper;

final class ConditionHelper
{
    public static function isOneOfVariablesEmpty(mixed ...$variables): bool
    {
        foreach ($variables as $variable) {
            if (empty($variable)) {
                return true;
            }
        }

        return false;
    }
}
