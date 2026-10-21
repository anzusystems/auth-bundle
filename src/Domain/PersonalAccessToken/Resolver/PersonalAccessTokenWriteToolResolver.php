<?php

declare(strict_types=1);

namespace AnzuSystems\AuthBundle\Domain\PersonalAccessToken\Resolver;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;

final readonly class PersonalAccessTokenWriteToolResolver
{
    public function __construct(
        private ?McpToolAccessChecker $toolAccessChecker = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveWriteTools(): array
    {
        return $this->toolAccessChecker?->getWriteToolNames() ?? [];
    }

    /**
     * @return list<string>
     */
    public function resolveGrantableWriteTools(): array
    {
        return $this->toolAccessChecker?->getPermittedWriteToolNames() ?? [];
    }
}
