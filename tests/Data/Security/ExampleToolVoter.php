<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Tests\Data\Security;

use AnzuSystems\AuthBundle\Tests\Data\Mcp\ExampleTool;
use AnzuSystems\CommonBundle\Security\Voter\AbstractVoter;

/**
 * @template-extends AbstractVoter<string, null>
 */
final class ExampleToolVoter extends AbstractVoter
{
    protected function getSupportedPermissions(): array
    {
        return array_keys(ExampleTool::READ_ONLY_BY_NAME);
    }
}
